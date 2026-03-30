# AGENT PIPELINE INVESTIGATION REPORT

## SECTION 1 — RETRY MECHANISM

### 1.1 Retry Trigger Locations

| Location | File:Line | Trigger Condition | Retry Counter | Max Limit |
|----------|-----------|------------------|---------------|-----------|
| **Job Queue Retry** | `app/Jobs/ProcessPhase1Job.php:29` | Any unhandled exception | `$this->tries = 5` | **5 attempts** |
| **Job Queue Retry** | `app/Jobs/ProcessPhase2Job.php:22` | Any unhandled exception | `$this->tries = 5` | **5 attempts** |
| **Job Queue Retry** | `app/Jobs/ProcessPhase3Job.php:30` | Any unhandled exception | `$this->tries = 5` | **5 attempts** |
| **OpenRouter API Retry** | `app/Services/OpenRouter/OpenRouterService.php:39` | HTTP 408, 429, 500, 502, 503, 504 | `$this->retryAttempts = 3` (config) | **3 attempts** |
| **Agent Execution Retry** | `app/Services/Orchestration/LegalOrchestrator.php:179-248` | Any exception during agent execution | `$maxRetries = 3` (line 179) | **3 attempts per agent** |
| **Self-Correction Retry** | `app/Services/Agents/Phase2/Phase2BaseAgent.php:165` | Output validation violations (low confidence, abrogated statutes) | `MAX_CORRECTION_ATTEMPTS = 3` (line 22) | **3 attempts per agent** |

### 1.2 Shared Retry Handler

**Answer: SEPARATE HANDLERS per trigger type**

1. **Laravel Queue System** - Handles job-level retries via `$tries` and `$backoff` properties in ProcessPhase*Job classes
2. **OpenRouterService** - Handles API-level retries in `complete()` and `completeStream()` methods
3. **LegalOrchestrator** - Handles agent-level retries in `runPhase2()` loop
4. **Phase2BaseAgent** - Handles self-correction retries in `executeWithSelfCorrection()` method

### 1.3 Confidence-Score-Based Retries

**Condition Checked:**
- From `Phase2BaseAgent.php:244-254`:
```php
if (preg_match_all('/"confidence"\s*:\s*([\d.]+)/', $output, $matches)) {
    foreach ($matches[1] as $score) {
        if ((float) $score < static::MIN_CONFIDENCE_THRESHOLD && (float) $score > 0) {
            $violations[] = [
                'type' => 'low_confidence',
                'detail' => "Confidence score {$score} is below threshold " . static::MIN_CONFIDENCE_THRESHOLD,
```

**Prompt Modification on Retry:**
- YES - The system appends error context to the prompt before retrying (lines 167-169):
```php
if (!empty($errorContext)) {
    $currentPrompt .= "\n\n---\n## Correction Context (سياق التصحيح) — Attempt {$attempt}\n\n" . $errorContext;
    $currentPrompt .= "\n\nPlease fix the above violations and regenerate your output.";
}
```

**Fallback Path:**
- **FOUND** - When all correction attempts are exhausted, the system proceeds with "best-effort output" instead of failing (lines 217-227):
```php
if ($attempt === static::MAX_CORRECTION_ATTEMPTS) {
    // All attempts exhausted — log warning but continue pipeline with best-effort output
    Log::warning("Agent {$this->agentNumber()} exhausted {$attempt} correction attempts, proceeding with best-effort output", [
        'case_id' => $case->id,
        'violations' => $violations,
    ]);
    $result['corrections_count'] = $attempt;
    $result['correction_details'] = array_map(fn($v) => $v['detail'], $violations);
    $result['self_correction_exhausted'] = true;
    return $result;
}
```

---

## SECTION 2 — CONFIDENCE SCORING

### 2.1 Confidence Score Production

**Location:** Extracted from LLM JSON output via regex parsing

**Code:** `Phase2BaseAgent.php:244`:
```php
if (preg_match_all('/"confidence"\s*:\s*([\d.]+)/', $output, $matches)) {
```

The confidence scores are **parsed from the LLM's own output** - the agents are instructed to include confidence scores in their JSON outputs (e.g., `DefenseStrategistAgent.php:54`, `LegalDrafterAgent.php:65`).

### 2.2 Confidence Threshold Definitions

| Threshold Type | Value | Location |
|----------------|-------|----------|
| **Minimum (hardcoded)** | `0.70` (70%) | `Phase2BaseAgent.php:23`: `protected const MIN_CONFIDENCE_THRESHOLD = 0.70;` |
| **Config default** | `0.70` (70%) | `config/legal.php:6`: `'confidence_threshold' => (float) env('CONFIDENCE_THRESHOLD', 0.70),` |
| **User-configurable** | Varies (0.50-0.90) | `app/Http/Requests/UpdateSettingsRequest.php:18`: `'confidence_threshold' => ['sometimes', 'numeric', 'min:0.50', 'max:0.90'],` |

### 2.3 Confidence Score Behavior

| Score Range | Code Path | Behavior |
|-------------|-----------|----------|
| **(a) Above threshold (≥0.70)** | Normal flow | Output accepted, no violation added |
| **(b) Between min and max** | N/A - only single threshold | Treated as below threshold if < 0.70 |
| **(c) Below minimum (< 0.70)** | `Phase2BaseAgent.php:244-254` | Triggers self-correction loop (up to 3 retries with prompt modification) |
| **(d) Exhausted retries** | `Phase2BaseAgent.php:217-227` | Proceeds with "best-effort output" + warning logged |

---

## SECTION 3 — AGENT EXECUTION MODEL

### 3.1 Agent Invocation

**Orchestration Layer:**
- **Class:** `LegalOrchestrator`
- **File:** `app/Services/Orchestration/LegalOrchestrator.php`
- **Method:** `runPhase2()` (line 143)

**Invocation Mechanism:**
- **Sequential for-loop** - NOT concurrent (line 156):
```php
for ($i = 1; $i <= 9; $i++) {
    // Execute agent $i sequentially
```

**NO PARALLEL EXECUTION FOUND** - No `Promise.all`, `asyncio.gather`, `ThreadPoolExecutor`, or similar concurrency mechanisms in the agent pipeline.

### 3.2 Sequential Enforcement

**CONFIRMED - Sequential execution is enforced:**

From `LegalOrchestrator.php:156-288`:
```php
for ($i = 1; $i <= 9; $i++) {
    // ... execute agent $i ...
    
    // Only after agent $i completes does the loop continue to $i+1
    if ($lastException !== null) {
        // Even on failure, continues to next agent (line 287: "Do NOT pause or return — continue to next agent")
    }
}
```

### 3.3 Output Handoff Between Agents

**Mechanism:** Full cumulative context via `buildContext()` method

From `Phase2BaseAgent.php:76-84`:
```php
$outputs = $case->outputs()->where('agent_number', '>=', 1)->where('agent_number', '<', $this->agentNumber())->orderBy('agent_number')->get();
foreach ($outputs as $o) {
    $c = $o->content;
    if ($c === null && $o->file_path && Storage::disk('local')->exists($o->file_path)) {
        $c = Storage::disk('local')->get($o->file_path);
    }
    $parts[] = "## {$o->filename}\n\n" . mb_substr((string) $c, 0, 25000);
}
```

**Answer: FULL cumulative context** - Each agent receives all previous agents' outputs (up to 25,000 chars each) plus intake text and documents.

---

## SECTION 4 — TIMEOUT & ERROR HANDLING

### 4.1 Individual LLM Call Timeout

| Setting | Value | Location |
|---------|-------|----------|
| **Timeout** | 300 seconds (5 min) | `config/openrouter.php:7`: `'timeout' => env('OPENROUTER_TIMEOUT', 300),` |
| **Agent-specific timeout** | 180 seconds (3 min) | `config/legal.php:10`: `'agent_timeout_seconds' => (int) env('AGENT_TIMEOUT_SECONDS', 180),` |

**On Timeout:**
- Treated as **retryable error** in `LegalOrchestrator.php:196-202`:
```php
if (!$executionResult['success']) {
    $timeoutMsg = $executionResult['timed_out'] ?? false 
        ? " (timeout after {$this->agentTimeoutSeconds}s)" 
        : '';
    throw new \RuntimeException($executionResult['error'] . $timeoutMsg);
}
```
- Triggers agent-level retry loop (up to 3 attempts)
- If all retries fail, **continues to next agent** (not fatal to pipeline)

### 4.2 Overall Pipeline Timeout

**NOT FOUND** - No overall timeout or deadline on:
- Complete case processing
- Phase 1 → Phase 2 → Phase 3 chain
- Per-case execution

### 4.3 Agent Failure Behavior

**Pipeline continues despite agent failures:**

From `ProcessPhase3Job.php:115-127`:
```php
} catch (\Throwable $e) {
    // ...
    Log::error("Phase 3 agent {$agentNum} failed, continuing pipeline: " . $e->getMessage(), ['case_id' => $case->id]);
    $exec->update([...]);
    $events->emitFailed($case->id, $agentNum, $agent->agentName(), $userMessage);
    // Do NOT throw — continue to next agent  <-- CONFIRMED
}
```

From `LegalOrchestrator.php:287`:
```php
// Do NOT pause or return — continue to next agent
```

**Error Logging:**
- Errors logged to `ErrorLog` model (database table)
- `LegalOrchestrator.php:269-275`:
```php
\App\Models\ErrorLog::create([
    'case_id' => $case->id,
    'error_type' => $isTimeout ? 'agent_timeout' : 'agent_failed',
    'error_message' => $errorMessage,
    'phase' => 2,
    'agent_number' => $i,
]);
```

**NO dead-letter queue** - Failed agents don't block pipeline; next agent starts regardless.

---

## SECTION 5 — SUMMARY

### A) ROOT CAUSE CANDIDATES

| Severity | Root Cause | File:Line |
|----------|-------------|-----------|
| **HIGH** | Agent failures do NOT block pipeline - next agent executes regardless | `LegalOrchestrator.php:287`, `ProcessPhase3Job.php:126` |
| **HIGH** | Confidence below threshold triggers self-correction but exhausts after 3 attempts then proceeds with "best-effort" | `Phase2BaseAgent.php:217-227` |
| **MEDIUM** | Job retry limit of 5 is shared across ALL phases - no per-phase budget | `ProcessPhase1Job.php:29`, `ProcessPhase2Job.php:22`, `ProcessPhase3Job.php:30` |
| **MEDIUM** | No overall pipeline timeout - case could run indefinitely | NOT FOUND |
| **LOW** | Multiple independent retry systems (job queue, OpenRouter API, agent execution, self-correction) with no unified policy | Multiple files |

### B) MISSING SAFEGUARDS

1. **No sequential gate between agents** - Agent N failure doesn't prevent Agent N+1 (explicit "continue" in code)
2. **No minimum confidence fallback path with warning** - System DOES have fallback (best-effort output) but proceeds silently with degraded quality
3. **No overall pipeline timeout** - Case can run indefinitely
4. **No retry budget tracking** - Each retry system operates independently
5. **No circuit breaker** - Failed agents retry indefinitely until max attempts
6. **No per-phase retry budget** - Phase 1, 2, and 3 share the same 5-job attempt limit

### C) OPEN QUESTIONS

1. **Runtime behavior of confidence parsing** - Does the regex `/"confidence"\s*:\s*([\d.]+)/` reliably extract scores from all agent outputs? Requires runtime logs to confirm.
2. **Actual retry counts at runtime** - Do agents actually retry 3 times on confidence violations? Requires logs to confirm.
3. **User notification on degraded output** - When best-effort output is used, is the user informed? Code shows logging but unclear if UI reflects this.
4. **Queue connection failure handling** - What happens if Redis/database queue fails mid-pipeline?
5. **Cost accumulation on retries** - Are retry costs tracked separately or aggregated? Code shows `costUsd` calculation but retry costs may inflate totals.

---

*Investigation completed. All findings based on static code analysis of Laravel project at `e:/Work/Automize/Projects/law-laravel-project`.*
