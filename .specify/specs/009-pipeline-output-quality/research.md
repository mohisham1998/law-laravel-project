# Research: 009-pipeline-output-quality

**Date**: 2026-03-27

## RQ-1: How to add system messages without breaking LLMServiceInterface?

**Decision**: No interface change needed. The `$messages` parameter already accepts `array<array{role: string, content: string}>`. System messages can be prepended as the first element with `role: 'system'`.

**Rationale**: Both `OpenRouterClient::chat()` and `PuterService::driverComplete()` pass `$messages` directly to the API. OpenRouter uses the OpenAI-compatible format which natively supports system messages. Puter's `ai-chat` driver also accepts OpenAI-format messages.

**Alternatives considered**: Adding a separate `$systemPrompt` parameter to the interface — rejected because it would break all existing callers and the current array format already supports it.

## RQ-2: How to refactor PromptBuilder for system + user message split?

**Decision**: Add two new methods to PromptBuilder: `buildSystemPrompt(int $agentNumber): string` and `buildUserPrompt(int $agentNumber, string $context): string`. Keep the existing `buildPromptForAgent()` as a backward-compatible wrapper that concatenates both into a single string.

**Rationale**: Agents can be migrated one at a time. Unmigrated agents continue calling `buildPromptForAgent()` and get the old behavior. Migrated agents call the two new methods separately and construct `[system, user]` messages.

**Alternatives considered**: Breaking `buildPromptForAgent()` to return a tuple — rejected because it would require migrating all agents simultaneously.

## RQ-3: Where does BriefPostProcessor fit in the pipeline?

**Decision**: Run BriefPostProcessor in two places:
1. After Agent 9 saves `09_final_brief_v2.md` — post-process and save as the cleaned v2
2. After Agent 12 saves `13_final_brief_v3.md` — post-process and save as the cleaned v3

The post-processor runs on the saved output before the next phase begins. It does NOT replace the agent execution — it's an additional step.

**Rationale**: Both Agent 9 and Agent 12 produce "final" briefs that should be clean. Running post-processing at both points ensures no matter where the pipeline stops, the latest brief is always clean.

**Alternatives considered**: Running only after Agent 12 — rejected because Phase 3 may fail and the user would see the unprocessed v2.

## RQ-4: How to integrate VectorSearchService into Phase1AnalysisAgent?

**Decision**: Add `VectorSearchService` as a constructor dependency to `Phase1AnalysisAgent`. In `buildContext()`, before the LLM call, extract keywords from intake text, run `searchMultiple()` with those keywords, and append RAG results to the context.

**Rationale**: Phase1AnalysisAgent already receives `LLMServiceInterface` via constructor injection. Adding another service follows the same pattern. The RAG search happens during context building, before the LLM call.

**Alternatives considered**: Using a separate pre-processing step outside the agent — rejected because it would complicate the Phase1 job and the context needs to be part of the prompt.

## RQ-5: How to update SKILL.md Agent 8 without breaking validation?

**Decision**: Two-step approach:
1. Update SKILL.md Agent 8 section to remove CASE/LAW markers and instruct pure Arabic citations
2. Update OutputValidator to remove `validateBriefCitations()` (which checks for LAW:{ref} markers) and replace with `validateArabicFinalBrief()` that checks for Arabic-only content

The old validation checked for marker presence. The new validation checks for Arabic purity. Both are structural checks — just different criteria.

**Rationale**: The CASE/LAW markers were an intermediate format intended for AI Erasure. By eliminating the markers at the source (Agent 8 instructions), the validation shifts from "did you use the markers correctly?" to "is the output pure Arabic?"

**Alternatives considered**: Keeping markers and improving AI Erasure — rejected because LLM-based string replacement is fundamentally unreliable.

## RQ-6: How does the quality gate interact with status management?

**Decision**: Use the existing `CompletedWithWarnings` status (`completed_with_warnings` enum value) for cases that fail the quality gate. The quality gate runs:
1. In `LegalOrchestrator` after Phase 2 Agent 9 completes — sets `Phase2Completed` or `CompletedWithWarnings`
2. In `ProcessPhase3Job` after Agent 12 completes — sets `Phase3Completed` or `CompletedWithWarnings`

**Rationale**: `CompletedWithWarnings` already exists in `CaseStatus` enum but is underused. This avoids adding a new enum value. The quality gate is a programmatic check (OutputValidator methods), not another LLM call.

**Alternatives considered**: Adding a new `CompletedWithIssues` status — rejected because `CompletedWithWarnings` already exists and conveys the same meaning.

## RQ-7: Puter temperature limitation

**Decision**: No special handling needed for system messages with Puter. The temperature limitation (Puter GPT-5 rejects custom temperature) only affects the `temperature` parameter, not the messages array. System messages work the same way with both providers.

**Rationale**: Confirmed by reading PuterService source — messages are passed as-is to the API. The temperature fallback is handled separately.
