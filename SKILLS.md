> ⚠️ File generato automaticamente da `php artisan fd:export-registry`.
> Non modificare a mano: le modifiche vanno fatte nei file skills/*/SKILL.md, poi riesegui l'export.
> Ultima generazione: 2026-05-05T15:32:31+00:00

# SKILLS.md — Skill Registry

## Indice

- [audio](#audio) — Transcribe voice messages with Whisper and generate speech with OpenAI TTS
- [contacts](#contacts) — Manage a personal contact list stored in memory
- [document-generator](#document-generator) — Generate QR codes, PDF documents, Word files (.docx), and Excel spreadsheets (.xlsx)
- [gmail](#gmail) — Read, search, and send emails via Gmail using the Gmail API v1
- [google-calendar](#google-calendar) — Read and manage Google Calendar events via the Google Calendar API v3
- [shopping-list](#shopping-list) — Manage grocery and shopping lists with categories and bought-status tracking
- [site-deploy](#site-deploy) — Deploy a web application via git pull, composer, and cache clear
- [social-media](#social-media) — Post to Facebook Pages and Instagram Business accounts via Meta Graph API
- [todo](#todo) — Manage personal to-do lists: create, list, complete, and delete tasks
- [vision](#vision) — Analyze images with GPT-4o vision and generate new images with DALL-E 3
- [whatsapp](#whatsapp) — Send WhatsApp messages via the Meta WhatsApp Business Cloud API

## Registry

### audio

```yaml
- name: audio
  display_name: "Audio"
  description: "Transcribe voice messages with Whisper and generate speech with OpenAI TTS"
  tools_required:
    - transcribe_audio
    - generate_audio
    - send_telegram_voice
  env_required:
    - OPENAI_API_KEY
  commands:
    - audio
```

### contacts

```yaml
- name: contacts
  display_name: "Contacts"
  description: "Manage a personal contact list stored in memory"
  tools_required:
    - memory_read
    - memory_write
  env_required: []
  commands: []
```

### document-generator

```yaml
- name: document-generator
  display_name: "Document Generator"
  description: "Generate QR codes, PDF documents, Word files (.docx), and Excel spreadsheets (.xlsx)"
  tools_required:
    - generate_qr
    - generate_pdf
    - generate_docx
    - generate_xlsx
  env_required: []
  commands:
    - document
```

### gmail

```yaml
- name: gmail
  display_name: "Gmail"
  description: "Read, search, and send emails via Gmail using the Gmail API v1"
  tools_required:
    - gmail_list
    - gmail_read
    - gmail_send
    - gmail_search
    - gmail_mark_read
    - gmail_trash
  env_required:
    - GOOGLE_CLIENT_ID
    - GOOGLE_CLIENT_SECRET
    - GOOGLE_GMAIL_REFRESH_TOKEN
  commands:
    - gmail
```

### google-calendar

```yaml
- name: google-calendar
  display_name: "Google Calendar"
  description: "Read and manage Google Calendar events via the Google Calendar API v3"
  tools_required:
    - google_calendar_list
    - google_calendar_create
    - google_calendar_delete
  env_required:
    - GOOGLE_CLIENT_ID
    - GOOGLE_CLIENT_SECRET
    - GOOGLE_REFRESH_TOKEN
    - GOOGLE_CALENDAR_ID
  commands:
    - calendar
```

### shopping-list

```yaml
- name: shopping-list
  display_name: "Shopping List"
  description: "Manage grocery and shopping lists with categories and bought-status tracking"
  tools_required:
    - shopping_add
    - shopping_items
    - shopping_bought
    - shopping_clear
  env_required: []
  commands:
    - shopping
```

### site-deploy

```yaml
- name: site-deploy
  display_name: "Site Deploy"
  description: "Deploy a web application via git pull, composer, and cache clear"
  tools_required:
    - bash
    - git_operation
    - composer_operation
    - npm_operation
    - laravel_artisan
  env_required: []
  commands: []
```

### social-media

```yaml
- name: social-media
  display_name: "Social Media"
  description: "Post to Facebook Pages and Instagram Business accounts via Meta Graph API"
  tools_required:
    - facebook_post
    - instagram_post
    - facebook_feed
  env_required:
    - FACEBOOK_PAGE_ACCESS_TOKEN
    - FACEBOOK_PAGE_ID
    - INSTAGRAM_BUSINESS_ACCOUNT_ID
  commands:
    - social
```

### todo

```yaml
- name: todo
  display_name: "Todo"
  description: "Manage personal to-do lists: create, list, complete, and delete tasks"
  tools_required:
    - todo_create
    - todo_list
    - todo_complete
    - todo_delete
  env_required: []
  commands:
    - todo
```

### vision

```yaml
- name: vision
  display_name: "Vision"
  description: "Analyze images with GPT-4o vision and generate new images with DALL-E 3"
  tools_required:
    - analyze_image
    - generate_image
    - send_telegram_image
  env_required:
    - OPENAI_API_KEY
  commands:
    - vision
```

### whatsapp

```yaml
- name: whatsapp
  display_name: "Whatsapp"
  description: "Send WhatsApp messages via the Meta WhatsApp Business Cloud API"
  tools_required:
    - whatsapp_send
  env_required:
    - WHATSAPP_PHONE_NUMBER_ID
    - WHATSAPP_ACCESS_TOKEN
  commands:
    - whatsapp
```

## Changelog

- [2026-05-05T15:32:31+00:00] Initial export. 11 skill registrate.