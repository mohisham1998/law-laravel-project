# Production-ready setup: real case scenario

This guide aligns you with the steps to run **real** legal cases and a **production-ready** RAG (law library) and queue.

---

## 0. Default seed: laws included

Running `php artisan db:seed` (or `migrate:fresh --seed`) seeds **default Saudi laws** from the `laws/` folder:

- **نظام الإثبات** (evidence)
- **نظام المرافعات الشرعية** (procedures)
- **اللائحة التنفيذية لنظام الإجراءات الجزائية** (criminal)
- **اللوائح التنفيذية لنظام المرافعات الشرعية** (procedures)

Articles are **parsed synchronously** during seed, so the RAG is **ready to match valid cases** immediately. Embedding jobs are queued; run the queue (Horizon or `queue:work`) to enable full semantic search. For development and testing, the seeded laws are realistic and sufficient for case flow.

To start with an **empty** law library and add only your own laws, run `php artisan law-library:clear --force` after seeding (see §2).

---

## 1. Prepare the RAG environment first (yes)

Phase 2 agents build legal context from the **Law Library** (مكتبة الأنظمة والقوانين). They use:

- **`law_registry`** – each law (e.g. “نظام الإثبات”)
- **`law_articles`** – parsed articles from uploaded law files (filled by the **queue** after you upload files)

So you **must** prepare the RAG before running real cases:

1. **Clear any seeded/demo laws** (see below).
2. **Add real laws** via the app (Law Library or الأنظمة والقوانين).
3. **Let the queue process** each law file so `law_articles` are populated.

Without this, Phase 2 will either see “لا توجد أنظمة في المكتبة” or outdated/demo content.

---

## 2. Clear seeded laws

To start with a **clean** law library (no demo data):

### Option A: Artisan command (recommended)

From the project root (or inside the app container):

```bash
php artisan law-library:clear
```

Confirm when prompted. To skip confirmation (e.g. in scripts):

```bash
php artisan law-library:clear --force
```

This:

- Deletes all **law registry** rows (and, via DB cascade, their `law_files`, `law_articles`, `law_embeddings`).
- Clears **law_search_cache**.

After this, the law library is empty. Add real laws via the UI and let the queue process them.

### Option B: Docker

```bash
docker compose exec app php artisan law-library:clear
# or
docker compose exec app php artisan law-library:clear --force
```

### Do not re-seed demo laws in production

- Do **not** run `php artisan db:seed --class=LawLibrarySeeder` in production if you want only real laws.
- You can keep `DatabaseSeeder` for a test user and sample cases if you want; for a clean production run, run only migrations and skip seeders, or seed only users.

---

## 3. Ensure the Laravel queue is working

Phase 1, Phase 2, and law file processing run via the **queue**. If the queue is not running, cases will stay in `phase1_pending` and law files will never get processed into articles.

### With Docker (Horizon)

- **Worker** service runs Horizon: `php artisan horizon`.
- After `docker compose up -d`, ensure the **worker** containers are up:

  ```bash
  docker compose ps
  ```

- Check Horizon dashboard (if enabled): e.g. `http://localhost:8000/horizon` (or the route you configured).
- Logs:

  ```bash
  docker compose logs worker --tail 50
  ```

### Without Docker

Run the queue in the background:

```bash
php artisan horizon
# or
php artisan queue:work --tries=3
```

### Quick check that the queue runs

1. Create a case (or upload a law file).
2. Within a short time, the case should move from `phase1_pending` to `phase1_processing`, then to `awaiting_laws` (for cases), or the law file should get `is_processed = true` and `law_articles` populated (for laws).
3. If nothing happens, check queue connection (e.g. Redis), Horizon/worker process, and `storage/logs/laravel.log`.

---

## 4. Steps for a real case scenario (order)

Use this order so RAG and queue are ready before you run real cases.

| Step | Action | Why |
|------|--------|-----|
| 1 | **Clear seeded laws** | `php artisan law-library:clear --force` | Start with empty RAG; no demo laws. |
| 2 | **Confirm queue is running** | `docker compose ps` and/or `docker compose logs worker` (or run Horizon locally) | Phase 1/2 and law processing depend on the queue. |
| 3 | **Add real laws** | Go to Law Library (e.g. `/law-library` or الأنظمة والقوانين), create a law, upload the official text file(s). | RAG content comes from here. |
| 4 | **Wait for law processing** | Check that each law file shows as processed (e.g. “processed” or articles count). | Phase 2 uses `law_articles`; until processing completes, context is incomplete. |
| 5 | **Optional: set AI model** | Settings → choose the model you want for production. | Cases use `model_used` (user’s selected model or config default). |
| 6 | **Create a real case** | Cases → Create; fill title, description (intake), upload real documents. | Intake and docs are the input for Phase 1 and Phase 2. |
| 7 | **Phase 1 runs** | Case goes to `phase1_processing` then `awaiting_laws`. | Queue runs `ProcessPhase1Job`; required laws are parsed and saved. |
| 8 | **Approve Phase 2** | On the case show page, use the approval modal and click “بدء المرحلة الثانية”. | Phase 2 starts only after user approval. |
| 9 | **Phase 2 runs** | Case goes to `phase2_processing`; 9 agents run in order (gate-by-file). | Queue runs `ProcessPhase2Job`; agents use RAG (law_registry + law_articles) and case outputs. |
| 10 | **Export / use result** | When status is `phase2_completed` (or `phase3_completed`), use PDF export and case outputs. | Production outcome. |

---

## 5. Checklist summary

- [ ] **RAG prepared**: Seeded laws cleared; real laws added via Law Library; law files processed (queue ran).
- [ ] **Queue running**: Horizon (or `queue:work`) is up and processing jobs.
- [ ] **Env**: `APP_ENV=production` (or as needed), `QUEUE_CONNECTION=redis`, Redis and DB reachable.
- [ ] **Model**: User’s selected model (or default) is the one you want for production.
- [ ] **Real case**: Create case with real intake and documents; approve Phase 2 after Phase 1; monitor until completed.

---

## 6. Important commands reference

| Task | Command |
|------|---------|
| Clear law library (clean RAG) | `php artisan law-library:clear` or `--force` |
| Run migrations | `php artisan migrate --force` |
| Clear caches | `php artisan view:clear && php artisan cache:clear` |
| Queue (Docker) | `docker compose up -d` (includes worker); `docker compose logs worker` |
| Queue (local) | `php artisan horizon` or `php artisan queue:work --tries=3` |

---

**Summary:** Prepare the RAG first (clear seeded laws, add real laws, let the queue process them), ensure the Laravel queue is running, then run real cases. The `law-library:clear` command gives you a clean slate so the app is production-ready with only the laws you add via the UI.
