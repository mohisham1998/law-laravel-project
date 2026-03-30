# Running the project (Docker)

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and Docker Compose
- Optional: copy `.env.example` to `.env` and set `APP_KEY`, `OPENROUTER_API_KEY`, etc. (compose already sets DB/Redis)

## Run with Docker

From the project root (`law-laravel-project`):

```bash
docker compose up --build
```

- **App:** http://localhost:8000  
- **PostgreSQL:** localhost:5433 (user `legal_user`, DB `legal_orchestrator`, password `secure_password_here`)  
- **Redis:** localhost:6379  

The first run will:

1. Build the app and worker images (Composer + npm build).
2. Start Postgres and Redis, then the app and Horizon worker.
3. Run migrations and `key:generate` if needed via the entrypoint.
4. **Clear view, cache, config, and route caches** on every container start so Blade/UI edits show without stale cache.

### UI / Blade edits (no cache)

- **On every start:** The entrypoint runs `view:clear`, `cache:clear`, `config:clear`, `route:clear`, so after `docker compose up` or `docker compose restart app` you get a clean slate.
- **Without restarting:** While the app is running, open **http://localhost:8000/dev/clear-views** (while logged in). That clears view and other caches so your latest Blade changes appear on the next page refresh. (Only available when `APP_ENV=local`.)

### Using a host `.env` file

Compose sets DB/Redis in the file; for `APP_KEY`, `OPENROUTER_API_KEY`, etc., use a `.env` file:

1. Copy: `cp .env.example .env` and edit as needed.
2. In `docker-compose.yml`, under the `app` and `worker` services, add:  
   `env_file: [.env]`  
   (Compose requires the file to exist if listed, so create it first.)

### Development with volume mount

The compose file mounts the project into the container (`.:/var/www/html`). After changing frontend assets, rebuild on the host so the app serves them:

```bash
npm ci && npm run build
```

Then refresh the page (or restart the app container).

### Run in background

```bash
docker compose up --build -d
```

### Stop and remove containers

```bash
docker compose down
```

To remove volumes (database and Redis data):

```bash
docker compose down -v
```

---

## Run locally (without Docker)

1. **Requirements:** PHP 8.2+, Composer, Node 20+, PostgreSQL, Redis.

2. **Setup:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   # Set DB_* and REDIS_* in .env for your local Postgres/Redis
   composer install
   npm install && npm run build
   php artisan migrate
   ```

3. **Run (dev):**
   ```bash
   composer run dev
   ```
   This starts the web server, queue listener, logs, and Vite dev server.

   Or run separately:
   ```bash
   php artisan serve
   php artisan queue:listen --tries=1 --timeout=0
   npm run dev
   ```
