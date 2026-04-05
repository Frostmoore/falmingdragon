# 🔥 FlamingDragon

Personal AI agent daemon controllable via Telegram. Multi-provider LLM routing (Anthropic, OpenAI, Ollama), skill system, agentic tool-use loop, and a monitoring dashboard.

---

## Deploy with Docker (one command)

### Prerequisites

- Docker + Docker Compose v2 installed on the server
- A public HTTPS URL pointing to the server (needed for Telegram webhook)
- A Telegram bot token (from [@BotFather](https://t.me/BotFather))
- At least one LLM API key (Anthropic or OpenAI)

### Steps

**1. Clone the repo and create your .env**

```bash
git clone <your-repo-url> flamingdragon
cd flamingdragon
cp .env.example .env
```

**2. Edit .env with your values**

```bash
nano .env   # or vim, or any editor
```

Minimum required values:

```env
APP_URL=https://your-domain.com
APP_KEY=                          # leave empty — auto-generated on first boot

FD_TELEGRAM_BOT_TOKEN=your_bot_token
FD_TELEGRAM_WEBHOOK_SECRET=some_random_32char_string
FD_TELEGRAM_ALLOWED_CHAT_IDS=your_telegram_chat_id

ANTHROPIC_API_KEY=sk-ant-...      # or OPENAI_API_KEY=

DB_PASSWORD=choose_a_strong_password
DB_ROOT_PASSWORD=choose_a_strong_root_password
```

> **How to get your Telegram chat ID:** start your bot, then open  
> `https://api.telegram.org/bot<TOKEN>/getUpdates` in a browser and look for `"id"` inside `"chat"`.

**3. Start everything**

```bash
docker compose up -d
```

This will:
- Build the PHP-FPM image
- Start MariaDB, Nginx, the app, and the queue worker
- Run migrations and seeders automatically on first boot

**4. Register the Telegram webhook**

```bash
curl -X POST https://your-domain.com/api/telegram/webhook/set \
  -d "token=your_bot_token&url=https://your-domain.com/api/telegram/webhook&secret=your_webhook_secret"
```

Or use the **Setup Wizard** in the dashboard at `https://your-domain.com/wizard`.

**5. Done — send a message to your bot on Telegram**

---

## Dashboard

Access the monitoring dashboard at `https://your-domain.com/dashboard`.

---

## Useful commands

```bash
# View logs
docker compose logs -f app
docker compose logs -f worker

# Restart a service
docker compose restart app

# Run artisan commands inside the container
docker compose exec app php artisan fd:heartbeat

# Update (pull new code and rebuild)
git pull
docker compose up -d --build

# Stop everything
docker compose down

# Wipe everything including the database
docker compose down -v
```

---

## Architecture

```
Internet → Nginx → PHP-FPM (app) → MariaDB
                        ↑
                   Queue Worker
```

| Container    | Image              | Role                              |
|--------------|--------------------|-----------------------------------|
| `fd_nginx`   | nginx:1.27-alpine  | Reverse proxy, static files       |
| `fd_app`     | (built)            | Laravel PHP-FPM                   |
| `fd_worker`  | (built)            | queue:work — async agent jobs     |
| `fd_db`      | mariadb:11         | Database                          |

---

## Local development (XAMPP / without Docker)

See [docs/local-dev.md](docs/local-dev.md) or configure via the Setup Wizard at `/wizard`.
