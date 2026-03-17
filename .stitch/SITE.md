# Saudi Legal Case Orchestrator – Stitch Site

**Project ID**: 18254556907662508752  
**API Base**: `/api/v1` (Laravel backend)

---

## Vision

A professional legal case management interface for Saudi legal professionals. Users create cases, upload documents, trigger AI analysis, upload required laws, run 9-agent processing, optionally run Phase 3 (Judge + Devil's Advocate), and view/export final briefs.

---

## Sitemap (8 Screens)

1. **dashboard-home** – Stats (total, processing, completed, failed) + case list with filters
2. **new-case-form** – Create case: title, intake text, document upload
3. **case-detail-view** – Case overview, documents, required laws, status, progress
4. **laws-upload-modal** – Modal to upload required laws (after Phase 1)
5. **output-viewer** – List outputs, view/download markdown/JSON
6. **error-log-viewer** – Table of errors with agent, type, details, fix
7. **agent-timeline** – Timeline of 11 agents with status (pending/running/done/failed)
8. **settings-page** – Model selector, confidence threshold, cost breakdown, token regen

---

## API Endpoints Used

- `GET /dashboard` – Stats
- `GET /cases` – List (paginated, filter by status, sort)
- `POST /cases` – Create case
- `GET /cases/{id}` – Case detail
- `POST /cases/{id}/laws` – Upload law
- `POST /cases/{id}/start-phase2` – Start Phase 2
- `POST /cases/{id}/start-phase3` – Start Phase 3
- `GET /cases/{id}/outputs` – List outputs
- `GET /cases/{id}/outputs/{id}` – Get output content
- `GET /cases/{id}/final-brief` – Final brief (md/pdf)
- `GET /cases/{id}/errors` – Error log
- `GET /settings`, `PATCH /settings`, `GET /settings/models`, `GET /settings/cost-breakdown`

---

## Roadmap

- Build all 8 screens per DESIGN.md
- Wire auth (Bearer token), 5-second polling for status
- Arabic RTL for case content where applicable
