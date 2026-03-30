# Data Model: Pipeline Quality Overhaul

**Date**: 2026-03-26 | **Feature**: 007-pipeline-quality-overhaul

## No Database Schema Changes

This feature modifies only the prompt assembly, validation logic, and configuration layers. No new tables, columns, or migrations are required. All changes operate on:

1. **In-memory prompt construction** (PromptBuilder)
2. **File-based validation** (OutputValidator reading case output files from `storage/app/cases/{id}/`)
3. **Configuration values** (config/legal.php)

## Key Data Structures

### Agent Config (config/legal.php)

```php
'agents' => [
    0  => ['temperature' => 0.3, 'max_tokens' => 4096],
    1  => ['temperature' => 0.3, 'max_tokens' => 8192],
    2  => ['temperature' => 0.2, 'max_tokens' => 8192],
    3  => ['temperature' => 0.2, 'max_tokens' => 8192],
    4  => ['temperature' => 0.3, 'max_tokens' => 8192],
    5  => ['temperature' => 0.3, 'max_tokens' => 8192],
    6  => ['temperature' => 0.3, 'max_tokens' => 8192],
    7  => ['temperature' => 0.3, 'max_tokens' => 8192],
    8  => ['temperature' => 0.3, 'max_tokens' => 16384],
    9  => ['temperature' => 0.2, 'max_tokens' => 16384],
    10 => ['temperature' => 0.3, 'max_tokens' => 8192],
    11 => ['temperature' => 0.3, 'max_tokens' => 8192],
    12 => ['temperature' => 0.3, 'max_tokens' => 16384],
]
```

### Validation Result (OutputValidator return type)

```php
[
    'valid' => bool,
    'violations' => [
        [
            'type' => 'hallucinated_statute' | 'quote_mismatch' | 'abrogated_article' |
                       'confidence_below_threshold' | 'missing_citation' | 'invalid_jsonl' |
                       'missing_brief_section',
            'severity' => 'critical' | 'major' | 'minor',
            'detail' => string,   // Arabic description of the violation
            'line' => ?int,       // Line number in output where violation found
            'statute_id' => ?string,
        ]
    ],
    'stats' => [
        'total_citations' => int,
        'valid_citations' => int,
        'hallucinated_citations' => int,
        'abrogated_citations' => int,
        'quote_mismatches' => int,
    ]
]
```

### Prompt Composition Structure

```
┌──────────────────────────────────────────────┐
│ System Role (Arabic legal agent identity)    │
├──────────────────────────────────────────────┤
│ General Rules (from SKILL.md lines 15-49)    │
│  - Language & Format                         │
│  - Citation Standards                        │
│  - Confidence Threshold                      │
│  - Anti-Hallucination Protocol               │
│  - Error Memory                              │
│  - Self-Correction                           │
├──────────────────────────────────────────────┤
│ Agent-Specific Section (from SKILL.md)       │
│  - Role table                                │
│  - Behavior rules                            │
│  - Output schemas                            │
├──────────────────────────────────────────────┤
│ Output Template (required format)            │
│  - Few-shot example (for agents 5, 6, 8)    │
├──────────────────────────────────────────────┤
│ Context Boundary Instruction                 │
│  "You may ONLY cite statutes listed below."  │
├──────────────────────────────────────────────┤
│ Case Context                                 │
│  - Intake text                               │
│  - Document excerpts                         │
│  - Relevant statute subset                   │
│  - Prior agent outputs                       │
│  - Error memory (if retrying)                │
└──────────────────────────────────────────────┘
```

## File Inventory (Modified / Created)

| File | Action | Purpose |
|------|--------|---------|
| `app/Services/Orchestration/PromptBuilder.php` | REWRITE | Agent-specific prompt extraction from SKILL.md |
| `app/Services/Orchestration/OutputValidator.php` | CREATE | Deterministic citation/structure validation |
| `app/Services/Orchestration/GateValidator.php` | MODIFY | Add deterministic checks at phase boundaries |
| `app/Services/Agents/Phase1AnalysisAgent.php` | MODIFY | Fix max_tokens, use focused prompt |
| `app/Services/Agents/Phase2/Phase2BaseAgent.php` | MODIFY | Config-driven params, integrate OutputValidator |
| `app/Services/Agents/Phase2/LeadCounselAgent.php` | MODIFY | Focused prompt + output template |
| `app/Services/Agents/Phase2/EvidenceManagerAgent.php` | MODIFY | Focused prompt + output template |
| `app/Services/Agents/Phase2/ChainOfCustodyAgent.php` | MODIFY | Focused prompt + output template |
| `app/Services/Agents/Phase2/TimelineExtractorAgent.php` | MODIFY | Focused prompt + output template |
| `app/Services/Agents/Phase2/LawManagerAgent.php` | MODIFY | Focused prompt + template + few-shot |
| `app/Services/Agents/Phase2/StatuteMatcherAgent.php` | MODIFY | Focused prompt + template + few-shot + validator |
| `app/Services/Agents/Phase2/DefenseStrategistAgent.php` | MODIFY | Focused prompt + output template |
| `app/Services/Agents/Phase2/LegalDrafterAgent.php` | MODIFY | Focused prompt + template + few-shot + validator |
| `app/Services/Agents/Phase2/QualityAssuranceAgent.php` | MODIFY | Focused prompt + deterministic checks |
| `app/Services/Agents/Phase3/JudgeAgent.php` | MODIFY | Focused prompt |
| `app/Services/Agents/Phase3/DevilsAdvocateAgent.php` | MODIFY | Focused prompt + temp fix |
| `app/Services/Agents/Phase3/FortificationAgent.php` | MODIFY | Focused prompt |
| `config/legal.php` | MODIFY | Add per-agent config block |
