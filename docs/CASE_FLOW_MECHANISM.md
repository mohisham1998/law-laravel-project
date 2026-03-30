# Case processing flow – how it works

This document describes how legal cases move from creation to completion and how documents, phases, and agents are wired.

---

## 1. Is the cases logic working as intended?

**Yes.** The implementation matches the intended flow:

- **Create case** → attachments stored under the case, intake written, Phase 1 job dispatched.
- **Phase 1** → analysis runs, required laws are parsed and saved, case moves to **awaiting_laws**.
- **User approval** → modal on case show; user clicks “بدء المرحلة الثانية” → Phase 2 job dispatched.
- **Phase 2** → 9 agents run in order with **gate-by-file** (each agent waits for previous outputs); on success → **phase2_completed**; on failure after 3 retries → **paused**.
- **Concurrent limit** → max 3 cases per user in `phase1_pending` / `phase1_processing` / `phase2_processing`.
- **Retry** → from case show, user can retry (Phase 1 or Phase 2) or abort.
- **Documents** → case attachments are `CaseDocument`; they appear in “مجلدات القضايا” and in the documents page filtered by case.

---

## 2. End‑to‑end mechanism

### 2.1 Create case (`CaseController::store`)

1. **Concurrent check (FR-017)**  
   Count user’s cases in `phase1_pending`, `phase1_processing`, `phase2_processing`.  
   If ≥ 3 → redirect with error: “الحد الأقصى ٣ قضايا قيد المعالجة”.

2. **Validation**  
   `title` (required), optional `description`, `client_name`, `category`, `attachments[]` (files, max 50MB, allowed MIMEs).

3. **Create `LegalCase`**  
   - `status = phase1_pending`, `phase = 1`  
   - `model_used` = user’s `selected_model` or `config('openrouter.default_model')`  
   - `skill_version` / `skill_hash` from config  

4. **Intake and attachments**  
   - `cases/{case_id}/intake.txt` = `description` (so Phase 2 gate has intake).  
   - Each uploaded file → stored under `cases/{case_id}/` and a `CaseDocument` row with `case_id`, `filename`, `file_path`, `mime_type`, `file_size`.

5. **Dispatch**  
   `ProcessPhase1Job::dispatch($case)`.

6. **Redirect**  
   `redirect()->route('cases.show', $case)`.

---

### 2.2 Phase 1 (`ProcessPhase1Job`)

1. **Status**  
   Set `status = phase1_processing`, `phase = 1`.

2. **Gate**  
   `GateValidator::validatePhase1Gate($case)` → must have non‑empty `intake_text` (from description). If not → `status = failed`, return.

3. **Events**  
   Emit `agent.started` for agent 0 (تحليل القضية).

4. **Run Phase 1 agent**  
   `Phase1AnalysisAgent::execute($case)`:
   - Builds context (intake + case docs).
   - Calls OpenRouter with model from `$case->model_used`.
   - Parses LLM response for a JSON block `required_laws: [{ law_name, reason }]`.
   - For each item → `RequiredLaw::create([ case_id, law_name, reason, is_uploaded => false ])`.
   - Writes `cases/{case_id}/outputs/00_required_laws.md` and creates `CaseOutput` for agent 0.

5. **Cost/tokens**  
   TokenTracker and CostCalculator update case and user.

6. **Status**  
   Set `status = awaiting_laws`, `phase = 1`, `progress_percentage = 100`.

7. **On exception**  
   Set `status = failed`, rethrow.

So after Phase 1: case is **awaiting_laws**; required laws are in DB and `00_required_laws.md` is on disk. Phase 2 is **not** started automatically.

---

### 2.3 User approval and start Phase 2 (`CaseController::startPhase2`)

1. **When**  
   User opens case show; if `status === awaiting_laws`, the **phase2-approval modal** is shown (required laws listed).

2. **Action**  
   User submits the form → `POST /cases/{case}/start-phase2`.

3. **Check**  
   `$case->status` must be `awaiting_laws`. Otherwise redirect with error.

4. **Update**  
   `status = phase2_pending`, `phase = 2`.

5. **Dispatch**  
   `ProcessPhase2Job::dispatch($case)`.

6. **Redirect**  
   Back to case show with success message.

So Phase 2 starts **only** after user approval from the case show page.

---

### 2.4 Phase 2 – 9 agents (`ProcessPhase2Job` → `LegalOrchestrator::runPhase2`)

1. **Status**  
   Set `status = phase2_processing`, `phase = 2`, `started_at = now()`.

2. **Loop agents 1..9**  
   For each agent number `i`:

   - Set `current_agent = i`, `progress_percentage` from progress.
   - Create `AgentExecution` (case_id, agent_number, agent_name, status = InProgress, started_at).
   - Emit `agent.started`.
   - **Gate (FR-008)**  
     `GateValidator::validateGateForAgent($case, $i)`:
     - Requires `intake.txt` and all previous agents’ output files (e.g. for agent 5: `intake.txt`, `01_lead_plan.md` … `04_timeline.json`).
     - Paths under `cases/{case_id}/outputs/` (and intake under `cases/{case_id}/`).
   - **Retries**  
     Up to 3 attempts. On failure: update execution to Retrying and try again. If gate fails, throw (so retry runs).
   - **Execute**  
     `$agent->execute($case)` → calls OpenRouter, writes this agent’s output file(s), creates `CaseOutput` rows.
   - On success: update `AgentExecution` (Completed, tokens, cost, duration), emit `agent.completed`.
   - On failure after 3 retries: set execution to Failed, emit `agent.failed`, set **`status = paused`** (FR-016), **return** (no further agents).

3. **After all 9 succeed**  
   - Update case: `current_agent = 9`, `progress_percentage = 100`, `status = phase2_completed`, `completed_at`, total tokens/cost.
   - `CaseMetrics::upsertForCase($case)`.
   - Update user tokens/cost.

So: **gate-by-file** is enforced; each agent sees intake + previous outputs. On any agent failing 3 times, the case **pauses** and the user can retry or abort.

---

### 2.5 Phase 3 (optional)

- **When**  
  Case is `phase2_completed`. Phase 3 is started via **API** (`Api\CaseController::startPhase3`), not from the Blade case show by default.

- **Job**  
  `ProcessPhase3Job`: runs Judge + DevilsAdvocate agents, writes final outputs, then sets `status = phase3_completed` and updates metrics.

- **PDF export**  
  Available when case is `phase2_completed` or `phase3_completed`; uses final brief file (e.g. `09_final_brief_v2.md` or Phase 3 output) to generate RTL PDF.

---

### 2.6 Retry and abort (`CaseController::retryAgent`, `abort`)

- **Retry**  
  Allowed when status is `failed` or `paused`. Clears failure state; if `phase >= 2` → set `phase2_pending` and dispatch `ProcessPhase2Job`; else set `phase1_pending` and dispatch `ProcessPhase1Job`. Uses current user `selected_model`.

- **Abort**  
  Allowed when status is `failed`, `paused`, `phase1_processing`, or `phase2_processing`. Sets `status = cancelled`.

---

### 2.7 Documents and “مجلدات القضايا”

- **Case attachments**  
  Stored as `CaseDocument` (case_id, filename, file_path, mime_type, file_size). Files live under `storage/app/cases/{case_id}/`.

- **Documents page**  
  - Cases list: user’s cases with `documents_count`.  
  - Selecting a case filters `CaseDocument::where('case_id', $selectedCaseId)`.  
  - Upload (documents.store) requires a case; new file is stored under that case and a `CaseDocument` is created.

- **Phase 2**  
  Agents read from `cases/{case_id}/` (intake + uploaded docs) and write under `cases/{case_id}/outputs/`. No truncation of case or document data by the cases logic; only the UI may show truncated names.

---

## 3. Status flow (summary)

```
create → phase1_pending
    → (ProcessPhase1Job) phase1_processing
    → success: awaiting_laws   [user sees approval modal]
    → failure: failed

awaiting_laws + user clicks "بدء المرحلة الثانية"
    → phase2_pending
    → (ProcessPhase2Job) phase2_processing
    → all 9 agents ok: phase2_completed
    → any agent fails 3x: paused

(failed/paused) + user clicks "إعادة المحاولة"
    → phase1_pending or phase2_pending → job dispatched again

(failed/paused/phase1_processing/phase2_processing) + user clicks "إلغاء"
    → cancelled
```

---

## 4. Important files

| Purpose              | File(s) |
|----------------------|--------|
| Create case, limits  | `App\Http\Controllers\CaseController` (`store`, concurrent check) |
| Phase 1 job          | `App\Jobs\ProcessPhase1Job` |
| Phase 1 agent        | `App\Services\Agents\Phase1AnalysisAgent` (required_laws parse + RequiredLaw) |
| Start Phase 2 (UI)   | `CaseController::startPhase2`, route `cases.start-phase2` |
| Phase 2 job          | `App\Jobs\ProcessPhase2Job` |
| Phase 2 orchestration| `App\Services\Orchestration\LegalOrchestrator::runPhase2` |
| Gate-by-file         | `App\Services\Orchestration\GateValidator` |
| Phase 2 approval UI  | `resources/views/components/phase2-approval-modal.blade.php`, included in case show |
| Case show            | `resources/views/pages/cases/show.blade.php` |
| Documents list       | `App\Http\Controllers\DocumentController::index`, `CaseDocument` |

---

**Conclusion:** The cases logic is working as designed: create → Phase 1 → awaiting_laws → user approval → Phase 2 (9 agents, gate-by-file, pause on failure) → phase2_completed (and optionally Phase 3 via API). Size and time on the documents UI are layout/alignment concerns only; the case and document data are not truncated by the backend.
