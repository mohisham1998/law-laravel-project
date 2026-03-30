# Quickstart: LLM Provider Switch — OpenRouter & Puter

**Branch**: `008-puter-provider-switch`

## Prerequisites

- Docker + docker-compose running (`docker compose up -d`)
- Queue worker running (`docker compose exec -d app php artisan queue:work --sleep=3 --tries=3`)
- Existing `.env` with `OPENROUTER_API_KEY` set

## Setup Steps

### 1. Run migration

```bash
docker compose exec app php artisan migrate
```

Verifies: `users` table has `llm_provider`, `puter_model`, `puter_disclosure_acknowledged` columns.

### 2. Clear caches

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan view:clear
docker compose exec app php artisan cache:clear
```

### 3. Test OpenRouter is still working

1. Open `http://localhost:8000/settings`
2. Confirm "OpenRouter" is selected by default
3. Run a case — pipeline should complete normally

### 4. Test Puter provider

1. Open Settings → Select "Puter"
2. Click "Connect Puter Account" → Puter login modal should appear
3. Sign in or create a Puter account
4. Confirm connection status shows "متصل" (Connected)
5. Select a Puter model from the dropdown
6. Check the "أفهم وأوافق" disclosure checkbox
7. Save settings
8. Submit a new case — pipeline should route through Puter

## Environment Variables

No new env vars required. Optional override:

```env
PUTER_API_BASE_URL=https://api.puter.com   # default
```

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Puter model dropdown empty | Check browser console for `/api/v1/settings/puter-models` response |
| "X-Puter-Token missing" error | Go to Settings → reconnect Puter account |
| Case stuck in queue | Check `docker compose exec app php artisan queue:work` is running |
| OpenRouter regression | Confirm `llm_provider = 'openrouter'` in `users` table for test user |
