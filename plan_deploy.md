# plan_deploy.md — Deploy di FlamingDragon in produzione

**Creato:** 2026-07-31
**Stato generale:** ⬜ Non iniziato

---

## Fase 1 — Deployare FlamingDragon sul server

- [ ] **Fase 1 — Deploy in produzione**

**Obiettivo:** portare FlamingDragon fuori da XAMPP locale e metterlo sul server, raggiungibile
da Telegram 24/7.

Oggi gira solo su `E:\coding\XAMPP\htdocs\flamingdragon` con Apache locale. Finché sta lì il
webhook Telegram non è raggiungibile da internet e il bot funziona solo a PC acceso.

### Cosa comporta

**Bloccante — da risolvere prima di esporre qualsiasi cosa:**

- [ ] Dashboard e `/api/fd/*` **non hanno autenticazione** (nessun login, nessun token).
      Vanno messe dietro VPN, IP allowlist o Cloudflare Access. Chi arriva sulla dashboard può
      usare l'editor AI, che **riscrive `ToolExecutor.php`**.
- [ ] `APP_DEBUG=false` — con `true`, `AppServiceProvider::boot()` disattiva la verifica dei
      certificati SSL **per tutte** le chiamate HTTP dell'app.

**Setup:**

- [ ] PHP ≥ 8.2, MariaDB, `composer install --no-dev -o`
- [ ] `.env` completo (elenco chiavi in [codebase_reference.md](codebase_reference.md) §14.2)
- [ ] `php artisan migrate` + `db:seed`
- [ ] `php artisan db:seed --class=RiskCategoryMappingSeeder` — **a parte**, non è in
      `DatabaseSeeder` (§17 T10)
- [ ] `php artisan storage:link` — serve per media, `generated/`, logo
- [ ] Verificare `security.allowed_base_paths`: sul server Linux i path in config
      (`/var/www`, `/home/ubuntu`) finalmente hanno senso, ma devono combaciare con la
      directory reale del deploy (§18 D3)

**Servizi permanenti:**

- [ ] Queue worker `php artisan fd:worker` come servizio systemd (serve per i comandi `async`:
      `deploy_site`, `create_skill`, `morning_briefing`, `run_script`)
- [ ] Cron `* * * * * php artisan schedule:run` — è quello che fa girare `fd:heartbeat` ogni
      30 min

**Telegram:**

- [ ] URL pubblico HTTPS + `FD_TELEGRAM_WEBHOOK_SECRET` valorizzato (se è vuoto il controllo
      del secret viene **saltato del tutto**)
- [ ] Registrare il webhook dal wizard (`/wizard`) o via `setWebhook`
- [ ] Verificare che `FD_TELEGRAM_ALLOWED_CHAT_IDS` contenga il chat ID giusto — se è vuoto
      nessuno può usare il bot

**Verifica finale:**

- [ ] `GET /api/fd/health` risponde
- [ ] Un messaggio su Telegram arriva e torna una risposta
- [ ] Un comando `async` viene preso dal worker
- [ ] `php artisan fd:backup` funziona sul server

---

## Note

I bug e il debito tecnico stanno in [todo.md](todo.md). Nulla di quello è bloccante per il
deploy, tranne il punto 7 (nessuna auth) che è ripetuto qui sopra perché **qui** diventa
bloccante davvero.
