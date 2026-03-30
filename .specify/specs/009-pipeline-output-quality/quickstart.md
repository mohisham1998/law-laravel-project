# Quickstart: 009-pipeline-output-quality

**Date**: 2026-03-27

## What This Feature Changes

This feature improves the quality of legal brief output from the 13-agent pipeline by:
1. Adding system messages to all LLM calls (persona anchoring)
2. Adding RAG search to Agent 0 (better law identification)
3. Enforcing Arabic-only output (eliminating English/JSON artifacts)
4. Post-processing briefs deterministically in PHP
5. Adding chain-of-thought to key agents
6. Halting pipeline on critical agent failures
7. Increasing context budgets for law content
8. Adding a quality gate before case completion

## How to Test

### Prerequisites
- Existing seeded law library (run `php artisan db:seed --class=LawLibrarySeeder`)
- Queue worker running (`php artisan queue:work`)
- Dev server running (`php artisan serve`)

### Test Flow
1. Create a new case with Arabic intake text describing a legal dispute
2. Upload at least one document
3. Submit for Phase 1 processing
4. Approve laws and start Phase 2
5. Monitor real-time agent output via SSE
6. After completion, verify:
   - Final brief is pure Arabic (no English terms, no JSON, no markers)
   - Brief has 8 mandatory sections starting with بسم الله الرحمن الرحيم
   - Defense arguments contain structured syllogisms
   - Case status is `phase3_completed` (not `completed_with_warnings`)

### Verify System Messages
Check Laravel logs during processing — each agent should log its model and message structure showing both `system` and `user` roles.

### Verify Quality Gate
To test the failure path: temporarily inject an English sentence into Agent 8's output template. The quality gate should catch it and set status to `completed_with_warnings`.

## Files Modified (Summary)

| Area | Files |
|------|-------|
| Prompt Architecture | `PromptBuilder.php`, `SKILL.md` |
| Agent Base Classes | `Phase2BaseAgent.php`, `Phase1AnalysisAgent.php` |
| Phase 3 Agents | `JudgeAgent.php`, `DevilsAdvocateAgent.php`, `FortificationAgent.php` |
| Validation | `OutputValidator.php` |
| Orchestration | `LegalOrchestrator.php` |
| Jobs | `ProcessPhase2Job.php`, `ProcessPhase3Job.php` |
| New Services | `BriefPostProcessor.php`, `FinalArabicBriefComposer.php` |
| Config | `config/legal.php` (context caps) |
