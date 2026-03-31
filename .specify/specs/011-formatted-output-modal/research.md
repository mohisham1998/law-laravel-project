# Research: 011-formatted-output-modal

## R-001 — Markdown Rendering Library

**Decision**: `marked.js` v9+ via CDN
**Rationale**: Zero dependencies, < 20kb, CDN-ready, Unicode-safe (Arabic), supports all required Markdown syntax. No build step required. Already used as pattern in similar Laravel Blade projects.
**CDN URL**: `https://cdn.jsdelivr.net/npm/marked/marked.min.js`
**Alternatives considered**: `markdown-it` (heavier), `showdown.js` (less maintained), server-side PHP Markdown (adds unnecessary API round-trip)

## R-002 — Output Data Source for Modal

**Decision**: `dbOutputsByAgent` JS variable (already injected by `agent-timeline-live.blade.php`) + live streaming DOM elements `#agent-stream-{N}`
**Rationale**: All pipeline Markdown outputs are already available on the page in the `dbOutputsByAgent` object (keyed by agent number). For cases completed before page load, `dbOutputsByAgent` has all content. For cases completing live, streaming content is in `#agent-stream-{N}` span elements.
**Ordering**: Iterate agents 1→9 (matching pipeline order); skip empty outputs; join with `\n\n---\n\n` section dividers.

## R-003 — Auto-Open SSE Hook

**Decision**: Modify `agent-timeline-live.blade.php` to call `activateOutputButton(); openOutputModal();` in place of `activatePdfExportButton();`
**File location**: `resources/views/components/agent-timeline-live.blade.php` — inside the `case.status_changed` SSE handler, condition `data.status === 'phase3_completed' || data.status === 'completed_with_warnings'`
**Rationale**: Exact same trigger point as PDF button activation — no new events needed.

## R-004 — Arabic Button Label

**Decision**: `عرض النتائج` (enabled) / `عرض النتائج (غير متاح)` (disabled)
**Rationale**: Consistent with existing Arabic verb-noun pattern: `عرض الجدول الزمني`, `عرض في المستندات`. Short and action-oriented.
**Icon**: `article` (Material Symbols Outlined) — represents formatted document view.

## R-005 — Sample Case Content

**Intake text**: Arabic legal brief — defense attorney (طارق بن زياد العتيبي) representing defendants (نورة وحصة) in a criminal case (defamation/harm charges) before Jeddah Criminal Court. Task: write a rebuttal memo and witness impeachment brief.
**Documents (9 files)**:
1. صحيفة الدعوى الابتدائية — claimant's initial brief
2. مذكرة الرد الجوابي الأولى — first defense reply memo
3. محضر ضبط الجلسة — session transcript with witness testimony
4. فهرس ملفات ومستندات القضية — case file index
5. المستند رقم (١) مستخرج إلكتروني من نظام ناجز — official Najiz extract
6. المستند رقم (٢) مستخرج رسمي من الأحوال المدنية — civil registry extract
7. نظام الإجراءات الجزائية — Criminal Procedure Law (for RAG)
8. نظام المرافعات الشرعية — Sharia Pleadings Law (for RAG)
9. نظام الإثبات — Evidence Law (for RAG)

**RAG validation signal**: Agent outputs (agents 4–6 typically) should reference article numbers from files 7–9.
**Notification validation signal**: `case.status_changed` with `phase3_completed` or `completed_with_warnings` should push to Redis and appear in notification bell.
