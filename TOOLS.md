> ⚠️ File generato automaticamente da `php artisan fd:export-registry`.
> Non modificare a mano: le modifiche vanno fatte nel DB/seeder, poi riesegui l'export.
> Ultima generazione: 2026-05-05T15:41:25+00:00

# TOOLS.md — Tool Registry

## Indice per categoria

- [audio](#audio) — `generate_audio`, `transcribe_audio`
- [calendario](#calendario) — `google_calendar_create`, `google_calendar_delete`, `google_calendar_list`
- [dati](#dati) — `cron_list`, `db_query`, `memory_read`, `memory_write`, `working_memory_append`, `working_memory_read`
- [documenti](#documenti) — `generate_docx`, `generate_pdf`, `generate_qr`, `generate_xlsx`
- [email](#email) — `gmail_list`, `gmail_mark_read`, `gmail_read`, `gmail_search`, `gmail_send`, `gmail_trash`, `send_email`
- [infrastruttura](#infrastruttura) — `composer_operation`, `git_operation`, `npm_operation`
- [rete](#rete) — `http_get`, `http_post`, `json_api`, `summarize_url`, `web_search`
- [sistema](#sistema) — `bash`, `file_delete`, `file_list`, `file_read`, `file_search`, `file_write`, `laravel_artisan`, `process_status`
- [skill](#skill) — `skill_read`
- [social](#social) — `facebook_feed`, `facebook_post`, `instagram_post`, `whatsapp_send`
- [spesa](#spesa) — `shopping_add`, `shopping_bought`, `shopping_clear`, `shopping_items`
- [telegram](#telegram) — `send_telegram_image`, `send_telegram_voice`, `telegram_send`
- [todo](#todo) — `todo_complete`, `todo_create`, `todo_delete`, `todo_list`
- [utility](#utility) — `weather`
- [visione](#visione) — `analyze_image`, `generate_image`, `image_generate`

## Registry

### audio

```yaml
- name: generate_audio
  display_name: "Text-to-Speech (OpenAI TTS)"
  description: "Generate speech from text using OpenAI TTS. Returns local path to the generated MP3."
  category: audio
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required:
    - OPENAI_API_KEY
```

```yaml
- name: transcribe_audio
  display_name: "Audio Transcription (Whisper)"
  description: "Transcribe an audio file to text using OpenAI Whisper."
  category: audio
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required:
    - OPENAI_API_KEY
```

### calendario

```yaml
- name: google_calendar_create
  display_name: "Google Calendar — Create Event"
  description: "Create a new Google Calendar event."
  category: calendario
  risk_level: moderate
  risk_category: null
  requires_confirmation: false
  env_required:
    - GOOGLE_CLIENT_ID
    - GOOGLE_CLIENT_SECRET
    - GOOGLE_REFRESH_TOKEN
    - GOOGLE_CALENDAR_ID
```

```yaml
- name: google_calendar_delete
  display_name: "Google Calendar — Delete Event"
  description: "Delete a Google Calendar event by ID."
  category: calendario
  risk_level: dangerous
  risk_category: file_delete
  requires_confirmation: true
  env_required:
    - GOOGLE_CLIENT_ID
    - GOOGLE_CLIENT_SECRET
    - GOOGLE_REFRESH_TOKEN
    - GOOGLE_CALENDAR_ID
```

```yaml
- name: google_calendar_list
  display_name: "Google Calendar — List Events"
  description: "List upcoming events from Google Calendar (requires OAuth2 credentials in .env)."
  category: calendario
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required:
    - GOOGLE_CLIENT_ID
    - GOOGLE_CLIENT_SECRET
    - GOOGLE_REFRESH_TOKEN
    - GOOGLE_CALENDAR_ID
```

### dati

```yaml
- name: cron_list
  display_name: "Cron List"
  description: "List all scheduled FlamingDragon tasks (Laravel scheduler)."
  category: dati
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: db_query
  display_name: "DB Query (read-only)"
  description: "Run a read-only SELECT query on the application database."
  category: dati
  risk_level: moderate
  risk_category: db_destructive
  requires_confirmation: true
  env_required: []
```

```yaml
- name: memory_read
  display_name: "Memory Read"
  description: "Read entries from the persistent memory store."
  category: dati
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: memory_write
  display_name: "Memory Write"
  description: "Write an entry to the persistent memory store."
  category: dati
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: working_memory_append
  display_name: "Working Memory — Append"
  description: "Append a timestamped line to WORKINGMEMORY.md. Auto-truncates to ~10.000 token limit (oldest lines removed first)."
  category: dati
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: working_memory_read
  display_name: "Working Memory — Read"
  description: "Read WORKINGMEMORY.md content. Pass last_lines=N to get only the most recent N entries."
  category: dati
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

### documenti

```yaml
- name: generate_docx
  display_name: "Word Document Generator"
  description: "Generate a .docx Word document (requires phpoffice/phpword)."
  category: documenti
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: generate_pdf
  display_name: "PDF Generator"
  description: "Generate a PDF from HTML content (requires barryvdh/laravel-dompdf)."
  category: documenti
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: generate_qr
  display_name: "QR Code Generator"
  description: "Generate a QR code image (via qrserver.com, no API key needed)."
  category: documenti
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: generate_xlsx
  display_name: "Excel Spreadsheet Generator"
  description: "Generate a .xlsx Excel spreadsheet (requires phpoffice/phpspreadsheet)."
  category: documenti
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

### email

```yaml
- name: gmail_list
  display_name: "Gmail — List Messages"
  description: "List recent or filtered Gmail messages (inbox, unread, etc.)."
  category: email
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required:
    - GOOGLE_CLIENT_ID
    - GOOGLE_CLIENT_SECRET
    - GOOGLE_GMAIL_REFRESH_TOKEN
```

```yaml
- name: gmail_mark_read
  display_name: "Gmail — Mark as Read"
  description: "Mark a Gmail message as read by ID."
  category: email
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required:
    - GOOGLE_CLIENT_ID
    - GOOGLE_CLIENT_SECRET
    - GOOGLE_GMAIL_REFRESH_TOKEN
```

```yaml
- name: gmail_read
  display_name: "Gmail — Read Message"
  description: "Read the full content of a Gmail message by ID. Marks it as read."
  category: email
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required:
    - GOOGLE_CLIENT_ID
    - GOOGLE_CLIENT_SECRET
    - GOOGLE_GMAIL_REFRESH_TOKEN
```

```yaml
- name: gmail_search
  display_name: "Gmail — Search"
  description: "Search Gmail using Gmail query syntax (from:, subject:, is:unread, etc.)."
  category: email
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required:
    - GOOGLE_CLIENT_ID
    - GOOGLE_CLIENT_SECRET
    - GOOGLE_GMAIL_REFRESH_TOKEN
```

```yaml
- name: gmail_send
  display_name: "Gmail — Send Email"
  description: "Compose and send an email via Gmail (OAuth2)."
  category: email
  risk_level: moderate
  risk_category: message_third_party
  requires_confirmation: true
  env_required:
    - GOOGLE_CLIENT_ID
    - GOOGLE_CLIENT_SECRET
    - GOOGLE_GMAIL_REFRESH_TOKEN
```

```yaml
- name: gmail_trash
  display_name: "Gmail — Move to Trash"
  description: "Move a Gmail message to trash by ID."
  category: email
  risk_level: dangerous
  risk_category: file_delete
  requires_confirmation: true
  env_required:
    - GOOGLE_CLIENT_ID
    - GOOGLE_CLIENT_SECRET
    - GOOGLE_GMAIL_REFRESH_TOKEN
```

```yaml
- name: send_email
  display_name: "Send Email"
  description: "Send an email via the configured SMTP mailer."
  category: email
  risk_level: moderate
  risk_category: message_third_party
  requires_confirmation: true
  env_required:
    - MAIL_MAILER
    - MAIL_HOST
    - MAIL_PORT
    - MAIL_USERNAME
    - MAIL_PASSWORD
    - MAIL_FROM_ADDRESS
```

### infrastruttura

```yaml
- name: composer_operation
  display_name: "Composer Operation"
  description: "Run composer install or update."
  category: infrastruttura
  risk_level: moderate
  risk_category: bash
  requires_confirmation: true
  env_required: []
```

```yaml
- name: git_operation
  display_name: "Git Operation"
  description: "Perform git operations: pull, status, log (no force push)."
  category: infrastruttura
  risk_level: moderate
  risk_category: git_push
  requires_confirmation: true
  env_required: []
```

```yaml
- name: npm_operation
  display_name: "NPM Operation"
  description: "Run npm install or npm run scripts."
  category: infrastruttura
  risk_level: moderate
  risk_category: bash
  requires_confirmation: true
  env_required: []
```

### rete

```yaml
- name: http_get
  display_name: "HTTP GET"
  description: "Perform an HTTP GET request to a URL."
  category: rete
  risk_level: moderate
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: http_post
  display_name: "HTTP POST"
  description: "Perform an HTTP POST request to a URL with a payload."
  category: rete
  risk_level: moderate
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: json_api
  display_name: "JSON API Call"
  description: "Make a GET/POST request to a JSON API and return the parsed response."
  category: rete
  risk_level: moderate
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: summarize_url
  display_name: "Summarize URL"
  description: "Fetch a URL and return a concise LLM-generated summary of the content."
  category: rete
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: web_search
  display_name: "Web Search"
  description: "Search the web via DuckDuckGo Instant Answer API and return results."
  category: rete
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

### sistema

```yaml
- name: bash
  display_name: "Bash Shell"
  description: "Execute a shell command with path restrictions enforced."
  category: sistema
  risk_level: dangerous
  risk_category: bash
  requires_confirmation: true
  env_required: []
```

```yaml
- name: file_delete
  display_name: "File Delete"
  description: "Delete a file at an allowed path."
  category: sistema
  risk_level: dangerous
  risk_category: file_delete
  requires_confirmation: true
  env_required: []
```

```yaml
- name: file_list
  display_name: "File List"
  description: "List the contents of a directory."
  category: sistema
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: file_read
  display_name: "File Read"
  description: "Read the contents of a file at an allowed path."
  category: sistema
  risk_level: moderate
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: file_search
  display_name: "File Search"
  description: "Search for files by name or content within allowed paths."
  category: sistema
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: file_write
  display_name: "File Write"
  description: "Write or create a file at an allowed path."
  category: sistema
  risk_level: moderate
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: laravel_artisan
  display_name: "Laravel Artisan"
  description: "Run a Laravel Artisan command."
  category: sistema
  risk_level: dangerous
  risk_category: bash
  requires_confirmation: true
  env_required: []
```

```yaml
- name: process_status
  display_name: "Process Status"
  description: "Check the status of running processes on the server."
  category: sistema
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

### skill

```yaml
- name: skill_read
  display_name: "Skill Read"
  description: "Read the SKILL.md file of an installed skill."
  category: skill
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

### social

```yaml
- name: facebook_feed
  display_name: "Facebook — Read Feed"
  description: "Read recent posts from a Facebook Page."
  category: social
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required:
    - FACEBOOK_PAGE_ID
    - FACEBOOK_PAGE_ACCESS_TOKEN
```

```yaml
- name: facebook_post
  display_name: "Facebook — Post"
  description: "Post a message (with optional link/image) to a Facebook Page."
  category: social
  risk_level: moderate
  risk_category: message_third_party
  requires_confirmation: true
  env_required:
    - FACEBOOK_PAGE_ID
    - FACEBOOK_PAGE_ACCESS_TOKEN
```

```yaml
- name: instagram_post
  display_name: "Instagram — Post Image"
  description: "Publish an image with caption to Instagram Business account."
  category: social
  risk_level: moderate
  risk_category: message_third_party
  requires_confirmation: true
  env_required:
    - INSTAGRAM_BUSINESS_ACCOUNT_ID
    - FACEBOOK_PAGE_ACCESS_TOKEN
```

```yaml
- name: whatsapp_send
  display_name: "WhatsApp — Send Message"
  description: "Send a WhatsApp message via Meta WhatsApp Business Cloud API."
  category: social
  risk_level: moderate
  risk_category: message_third_party
  requires_confirmation: true
  env_required:
    - WHATSAPP_PHONE_NUMBER_ID
    - WHATSAPP_ACCESS_TOKEN
```

### spesa

```yaml
- name: shopping_add
  display_name: "Shopping List — Add Item"
  description: "Add an item to a shopping list with optional category and quantity."
  category: spesa
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: shopping_bought
  display_name: "Shopping List — Mark Bought"
  description: "Mark a shopping list item as bought by ID."
  category: spesa
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: shopping_clear
  display_name: "Shopping List — Clear Bought"
  description: "Remove all bought items from a shopping list."
  category: spesa
  risk_level: moderate
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: shopping_items
  display_name: "Shopping List — View Items"
  description: "View items on a shopping list, optionally including bought items."
  category: spesa
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

### telegram

```yaml
- name: send_telegram_image
  display_name: "Send Telegram Image"
  description: "Send an image (URL or local path) to the Telegram user."
  category: telegram
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: send_telegram_voice
  display_name: "Send Telegram Voice/Audio"
  description: "Send an audio file as a voice or audio message to the Telegram user."
  category: telegram
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: telegram_send
  display_name: "Telegram Send"
  description: "Send a message to the authorized Telegram user."
  category: telegram
  risk_level: safe
  risk_category: message_third_party
  requires_confirmation: true
  env_required: []
```

### todo

```yaml
- name: todo_complete
  display_name: "Todo — Complete"
  description: "Mark a todo item as done by ID."
  category: todo
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: todo_create
  display_name: "Todo — Create"
  description: "Create a new todo item in a named list."
  category: todo
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: todo_delete
  display_name: "Todo — Delete"
  description: "Delete a todo item by ID."
  category: todo
  risk_level: moderate
  risk_category: null
  requires_confirmation: false
  env_required: []
```

```yaml
- name: todo_list
  display_name: "Todo — List"
  description: "List todos from a named list (or all lists)."
  category: todo
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

### utility

```yaml
- name: weather
  display_name: "Weather"
  description: "Get current weather for a location using the Open-Meteo free API (no key needed)."
  category: utility
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required: []
```

### visione

```yaml
- name: analyze_image
  display_name: "Image Analyzer (GPT-4o Vision)"
  description: "Analyze or describe an image using GPT-4o vision. Accepts a local file path or public URL."
  category: visione
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required:
    - OPENAI_API_KEY
```

```yaml
- name: generate_image
  display_name: "Image Generator (DALL-E 3)"
  description: "Generate an image with DALL-E 3. Returns the public image URL."
  category: visione
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required:
    - OPENAI_API_KEY
```

```yaml
- name: image_generate
  display_name: "Image Generate"
  description: "Generate an image via OpenAI DALL-E 3 and return the URL."
  category: visione
  risk_level: safe
  risk_category: null
  requires_confirmation: false
  env_required:
    - OPENAI_API_KEY
```

## Changelog

- [2026-05-05T15:41:25+00:00] Re-export (no structural changes).
- [2026-05-05T15:36:26+00:00] Added: working_memory_append, working_memory_read
- [2026-05-05T15:32:31+00:00] Initial export. 56 tool registrati.