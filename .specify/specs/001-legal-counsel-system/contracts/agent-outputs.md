# Agent Output Contracts

**Feature**: `001-legal-counsel-system` | **Date**: 2026-03-19

## Storage

- **Base path**: `storage/app/cases/{case_id}/outputs/`
- **Error memory**: `storage/app/cases/{case_id}/memory/errors_log.md`
- **Database mirror**: `case_outputs` table (content + metadata)

## Agent 0 — Phase 1 Analysis

### `00_required_laws.md`
- Markdown prose listing identified laws from RAG database
- Each law: official name, subject area, reason for relevance, abrogation status

## Agent 1 — Lead Counsel

### `01_lead_plan.md`
- Case summary, scope, acceptance criteria
- Expected output files per agent
- Strategic instructions for Agent 8 (three-tier defense)

### `01_acceptance_criteria.json`
```json
{
  "min_confidence": 0.70,
  "dual_citation_required": true,
  "max_unsupported_paragraphs": 0,
  "abrogated_articles_allowed": 0,
  "defense_tiers_required": 3,
  "preamble_required": true
}
```

## Agent 2 — Evidence Manager

### `02_chunks.jsonl`
One JSON object per line:
```json
{
  "chunk_id": "C001",
  "source_path": "docs/contract.txt",
  "section_id": "S01",
  "start_line": 1,
  "end_line": 45,
  "text": "...",
  "char_count": 1500
}
```
- Text: 1200-1800 characters per chunk
- Overlap: 200 characters between consecutive chunks

### `02_ingestion_report.md`
- Files processed with size and status
- Corrupted files flagged as `_needs_review`
- Error details for failed reads

## Agent 3 — Chain of Custody

### `03_statutes_index.jsonl`
One JSON object per line:
```json
{
  "statute_id": "IS-M11",
  "title": "نظام الإثبات",
  "article_no": "11",
  "content": "المادة الحادية عشرة: ...",
  "file_label": "evidence_law",
  "local_ref": "نظام الإثبات - المادة 11",
  "effective_year": "1443",
  "supersedes": [],
  "source": "rag_database",
  "law_registry_id": 1
}
```

### `03_conflict_warnings.md`
- List of detected abrogations or conflicts
- For each: old law, new law, affected articles, recommendation

### `03_chain_of_custody_summary.md`
- Document fingerprints (first/last 64 chars, line count, char count)
- File integrity assessment

## Agent 4 — Timeline Extractor

### `04_timeline.json`
```json
{
  "events": [
    {
      "id": "E001",
      "date": "2025-06-15",
      "date_raw": "١٥ يونيو ٢٠٢٥",
      "place": "الرياض",
      "parties": ["المدعي", "المدعى عليه"],
      "description": "...",
      "source_refs": ["C001", "C003"],
      "confidence": 0.92
    }
  ]
}
```

### `04_timeline.md`
- Markdown prose version of the timeline

### `04_entities_index.md`
- Named entities: persons, organizations, locations, dates

## Agent 5 — Law Manager

### `05_issues_to_statutes.md`
- Each event mapped to legal issue (strong/medium/weak)
- Associated statute references

### `05_procedural_notes.md`
- Jurisdiction, standing, limitation periods, res judicata analysis

### `05_adversary_evidence_analysis.md`
- Three-step challenge per opponent evidence: Fact → Legal Flaw → Effect

### `05_matching_guidelines.json`
```json
{
  "guidelines": [
    {
      "issue_id": "I001",
      "recommended_statutes": ["IS-M11", "CP-M45"],
      "search_keywords": ["إثبات", "شهادة"],
      "priority": "high"
    }
  ]
}
```

## Agent 6 — Statute Matcher

### `06_statutes_map.jsonl`
One JSON object per line:
```json
{
  "chunk_ref": "C001",
  "statute_ref": "IS-M11",
  "quoted_text": "المادة الحادية عشرة: ...",
  "rationale": "...",
  "confidence": 0.85,
  "status": "accepted",
  "supersession_check": "verified_not_abrogated"
}
```

### `06_accepted_matches.md`
- Accepted matches in readable prose

### `06_rejections.md`
- Rejected matches with reasons

### `06_gaps_and_todo.md`
- Items below 0.70 confidence
- Logical fallback references (Islamic legal maxims)

## Agent 7 — Defense Strategist

### `07_risk_matrix.md`
- Per claim: claim_id, law refs, penalty range, factors, gaps, aggregate confidence

### `07_defense_layers.md`
- Primary defense line
- Alternative defense line
- Consequential requests

### `07_charges_scenarios.json`
```json
{
  "scenarios": [
    {
      "charge_id": "CH001",
      "description": "...",
      "probability": 0.75,
      "defense_approach": "primary"
    }
  ]
}
```

### `07_mitigation_opportunities.md`
- Mitigating factors and burden-of-proof analysis

## Agent 8 — Legal Drafter

### `08_final_brief.md`
- Complete Arabic legal brief with CASE:{} and LAW:{} internal references
- Structure: Preamble → Introduction → Facts → Legal Framework → Defense → Requests → Closing → Appendices

### `08_defense_arguments.md`
- Each argument as legal syllogism: Major Premise → Minor Premise → Conclusion

### `08_arguments_index.json`
```json
{
  "arguments": [
    {
      "id": "ARG001",
      "type": "primary",
      "case_refs": ["C001", "C005"],
      "law_refs": ["IS-M11"],
      "syllogism": {
        "major_premise": "...",
        "minor_premise": "...",
        "conclusion": "..."
      }
    }
  ]
}
```

## Agent 9 — Quality Assurance

### `09_QA_summary.md`
- Checklist results (pass/fail per item)

### `09_violations.md`
- Critical, major, minor violations found

### `09_fixes_applied.json`
```json
{
  "fixes": [
    {
      "type": "reference_conversion",
      "original": "[LAW:{IS-M11}]",
      "replacement": "بصريح المادة (الحادية عشرة) من نظام الإثبات..."
    }
  ]
}
```

### `09_todo_back_to_agents.md`
- Items requiring upstream agent re-runs

### `09_final_brief_v2.md`
- Clean Arabic brief with all AI traces removed
- Only produced if no critical violations remain

## Agent 10 — Judge (Phase 3)

### `10_judge_notes.md`
- Formal requirements check, substantive critique, procedural objections
- Likely session questions, fatal weaknesses, preliminary leaning

## Agent 11 — Devil's Advocate (Phase 3)

### `11_devils_advocate_notes.md`
- Counter-evidence, stronger opponent articles, contradictions
- Procedural defenses, likely opponent evidence, success probability

## Agent 12 — Fortification (Phase 3)

### `12_fortification_plan.md`
- Classification of observations: critical / important / routine

### `12_responses_to_judge.md`
- Point-by-point responses to judge's concerns

### `12_counter_arguments.md`
- Counter-arguments to devil's advocate attacks

### `13_final_brief_v3.md`
- Hardened brief with legal dilemma paragraphs
- Full AI erasure applied
