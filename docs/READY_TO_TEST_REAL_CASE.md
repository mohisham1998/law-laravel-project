# Ready to test a real case

Use this checklist to confirm the app is ready for a full case run (Phase 1 → approve laws → Phase 2).

---

## Quick checklist

| Requirement | How to verify |
|-------------|----------------|
| **1. Database seeded** | Laws + articles are in DB. Run `php artisan db:seed` or `migrate:fresh --seed` once. |
| **2. Laws & articles** | 4 Saudi laws, 1,191 articles (from seed). Phase 2 uses these for context. |
| **3. Queue running** | Horizon/worker must be running so Phase 1 and Phase 2 jobs execute. |
| **4. OpenRouter** | `.env` has `OPENROUTER_API_KEY` for Phase 1 and Phase 2 agents. |
| **5. User** | Log in with seeded user: `test@example.com` / `password` (or your own). |

---

## 1. One-time setup (if not already done)

```bash
# With Docker
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed
```

This gives you:

- 4 law registries + 4 files + **1,191 articles** (ready for case matching)
- Test user: **test@example.com** / **password**
- 8 sample cases (you can use one or create a new case)

---

## 2. Ensure queue is running

Phase 1 and Phase 2 run in the queue. Without workers, cases stay in `phase1_pending` or never start Phase 2.

**Docker:**

```bash
docker compose ps
# Ensure "worker" (or horizon) is Up

docker compose logs worker --tail 30
# Should show Horizon running
```

**Local (no Docker):**

```bash
php artisan horizon
# or
php artisan queue:work
```

---

## 3. Optional: RAG semantic search (embeddings)

- **Phase 2 context:** Built from **law_registry + law_articles** (by law name). No embeddings needed for the main agent context.
- **Semantic search** (e.g. statute matching): Uses embeddings. After seed, embedding jobs are queued; run the queue so they complete. With Docker, the worker processes them automatically.

If you haven’t run the queue after seed, embeddings may still be building. Case flow still works; only embedding-based features improve once embeddings exist.

---

## 4. Test a real case (steps)

1. **Log in**  
   http://localhost:8000 → test@example.com / password

2. **Create a case** (or open an existing one)  
   - Title and description (intake) required.  
   - Optionally attach documents.  
   - Submit → case is created and Phase 1 job is dispatched.

3. **Phase 1**  
   - Case moves to **قيد التحليل** then **بانتظار الموافقة على القوانين**.  
   - Open the case; you should see required laws (e.g. نظام الإثبات، نظام المرافعات الشرعية).

4. **Approve and start Phase 2**  
   - In the case view, use the approval modal and click **بدء المرحلة الثانية**.  
   - Case moves to **المرحلة الثانية قيد المعالجة**.

5. **Phase 2**  
   - 9 agents run in order (gate-by-file).  
   - Progress and outputs appear on the case.  
   - On success → **المرحلة الثانية مكتملة**.

6. **If something fails**  
   - Check `docker compose logs worker` (or Horizon logs).  
   - Case may move to **متوقف** after 3 retries; you can retry from the case page.

---

## 5. Quick verification commands

Run inside the app container (or locally):

```bash
# Laws and articles
docker compose exec app php artisan tinker --execute="echo 'Laws: ' . App\Models\LawRegistry::count() . ', Articles: ' . App\Models\LawArticle::count();"

# Queue
docker compose exec app php artisan queue:monitor default
docker compose exec app php artisan horizon:status
```

You should see 4 laws, 1,191 articles, and queue/Horizon OK.

---

## Summary

You are ready to test a real case when:

- Migrations and seed have been run (laws + articles + user + optional sample cases).
- Queue (Horizon or `queue:work`) is running.
- `.env` has `OPENROUTER_API_KEY`.

Then log in, create or open a case, and go through: **Phase 1 → approve laws → Phase 2**.
