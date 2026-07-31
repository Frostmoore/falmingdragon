# codebase_reference.md — Atlante della codebase FlamingDragon

**Versione documento:** 1.0.0
**Generato il:** 2026-07-31
**Commit di riferimento:** `26a2b79` (branch `claude`)
**Copertura:** tutto il codice scritto da noi (`app/`, `database/`, `routes/`, `config/flamingdragon.php`, `resources/`, `skills/`, `tests/`). Le dipendenze in `vendor/` non sono documentate.

> **Come si usa questo documento.** È un atlante, non un riassunto. L'obiettivo è che tu possa
> capire, trovare e modificare il codice **senza aprire i file**. Se cerchi la firma di un metodo
> e non la trovi qui, il documento è rotto: segnalalo e correggilo.
>
> **Ordine di lettura consigliato:** §1 (dove sta cosa) → §3 (flusso runtime) → la sezione
> specifica che ti serve. Se stai per toccare qualcosa di rischioso, leggi **prima** §16
> (regole non negoziabili) e §17 (trappole).

---

## Indice

| § | Sezione |
|---|---|
| 1 | [Indice "dove sta cosa"](#1-indice-dove-sta-cosa) |
| 2 | [Albero dei file annotato](#2-albero-dei-file-annotato) |
| 3 | [Flusso end-to-end a runtime](#3-flusso-end-to-end-a-runtime) |
| 4 | [Schema del database](#4-schema-del-database) |
| 5 | [Enums](#5-enums) |
| 6 | [Models](#6-models) |
| 7 | [Services](#7-services) |
| 8 | [Controllers, Middleware, Jobs](#8-controllers-middleware-jobs) |
| 9 | [Rotte ed endpoint](#9-rotte-ed-endpoint) |
| 10 | [Comandi artisan](#10-comandi-artisan) |
| 11 | [Catalogo dei tool](#11-catalogo-dei-tool-58) |
| 12 | [Catalogo delle skill](#12-catalogo-delle-skill-11) |
| 13 | [Catalogo dei comandi allow-list](#13-catalogo-dei-comandi-allow-list-25) |
| 14 | [Configurazione e variabili d'ambiente](#14-configurazione-e-variabili-dambiente) |
| 15 | [Catalogo dei test](#15-catalogo-dei-test) |
| 16 | [Regole non negoziabili](#16-regole-non-negoziabili) |
| 17 | [Trappole già disinnescate](#17-trappole-già-disinnescate) |
| 18 | [Debito tecnico aperto](#18-debito-tecnico-aperto) |
| 19 | [Il perché delle scelte non ovvie](#19-il-perché-delle-scelte-non-ovvie) |
| 20 | [Cosa NON esiste](#20-cosa-non-esiste-ancora) |

---

## 1. Indice "dove sta cosa"

La porta d'ingresso. Cerchi X → vai al file Y.

| Cerchi… | File |
|---|---|
| Il loop agentico (LLM ↔ tool ↔ LLM) | [app/Services/Agent/AgentOrchestrator.php](app/Services/Agent/AgentOrchestrator.php) |
| L'implementazione di un tool (`bash`, `gmail_send`, …) | [app/Services/Agent/ToolExecutor.php](app/Services/Agent/ToolExecutor.php) |
| **Le definizioni JSON-schema dei tool viste dall'LLM** | `AgentOrchestrator::buildToolDefinitions()` — [AgentOrchestrator.php:257](app/Services/Agent/AgentOrchestrator.php#L257) |
| Chi decide sync vs async | `CommandRouter::resolveExecutionMode()` + [AgentSpawner.php](app/Services/Agent/AgentSpawner.php) |
| Il gate di conferma `/confirm` `/deny` | [app/Services/Security/ConfirmationGate.php](app/Services/Security/ConfirmationGate.php) |
| L'allow-list dei comandi | [app/Services/Security/AllowListGuard.php](app/Services/Security/AllowListGuard.php) |
| La validazione dei path (sandbox) | `SessionSandbox::validatePath()` — [SessionSandbox.php:63](app/Services/Security/SessionSandbox.php#L63) |
| Il system prompt assemblato | [app/Services/Llm/PromptBuilder.php](app/Services/Llm/PromptBuilder.php) |
| Il testo base del system prompt | [resources/prompts/agent_system.md](resources/prompts/agent_system.md) |
| Le chiamate HTTP ad Anthropic | [app/Services/Llm/Providers/AnthropicProvider.php](app/Services/Llm/Providers/AnthropicProvider.php) |
| Scelta del provider LLM a runtime | [app/Services/Llm/LlmRouter.php](app/Services/Llm/LlmRouter.php) |
| Ricezione messaggi Telegram | [app/Http/Controllers/Api/TelegramWebhookController.php](app/Http/Controllers/Api/TelegramWebhookController.php) |
| Guardia chat-ID + secret webhook | [app/Http/Middleware/TelegramAuthMiddleware.php](app/Http/Middleware/TelegramAuthMiddleware.php) |
| Invio messaggi/foto/audio a Telegram | [app/Services/Telegram/TelegramService.php](app/Services/Telegram/TelegramService.php) |
| Parsing di `/comando arg1 arg2` | `TelegramParser::parseCommand()` — [TelegramParser.php:62](app/Services/Telegram/TelegramParser.php#L62) |
| **Il classificatore NL (testo libero → comando)** | `TelegramWebhookController::interpretNaturalLanguage()` — [riga 314](app/Http/Controllers/Api/TelegramWebhookController.php#L314) |
| Memoria persistente + ricerca semantica | [app/Services/Memory/MemoryService.php](app/Services/Memory/MemoryService.php) |
| Embedding OpenAI + cosine similarity | [app/Services/Embeddings/EmbeddingService.php](app/Services/Embeddings/EmbeddingService.php) |
| Working memory (WORKINGMEMORY.md) | [app/Services/Memory/WorkingMemoryService.php](app/Services/Memory/WorkingMemoryService.php) |
| Parsing frontmatter SKILL.md | [app/Services/Skill/SkillParser.php](app/Services/Skill/SkillParser.php) **e** `Skill::parseFrontmatter()` **e** `ExportRegistryCommand::parseFrontmatter()` (⚠ tre implementazioni, vedi §18) |
| Generazione skill/tool via Telegram | [app/Services/Generator/GeneratorService.php](app/Services/Generator/GeneratorService.php) |
| Generazione skill/tool via dashboard | [app/Services/Generator/WebGeneratorService.php](app/Services/Generator/WebGeneratorService.php) |
| **Codice che riscrive ToolExecutor.php** | `WebGeneratorService::insertToolMethod()` / `insertDispatchEntry()` + `AIEditorService::applyToolModification()` |
| Editor AI di tool e skill (dashboard) | [app/Services/Dashboard/AIEditorService.php](app/Services/Dashboard/AIEditorService.php) |
| Lettura/scrittura di `.env` | [app/Services/Dashboard/EnvEditor.php](app/Services/Dashboard/EnvEditor.php) **e** `WizardController::writeEnvValues()` (⚠ due implementazioni) |
| Schema tabelle | [database/migrations/](database/migrations/) |
| Dati iniziali (tool, comandi, provider) | [database/seeders/](database/seeders/) |
| Mapping tool → categoria di rischio | [database/seeders/RiskCategoryMappingSeeder.php](database/seeders/RiskCategoryMappingSeeder.php) |
| Tutte le chiavi di configurazione FD | [config/flamingdragon.php](config/flamingdragon.php) |
| Rotte API | [routes/api.php](routes/api.php) |
| Rotte dashboard | [routes/web.php](routes/web.php) |
| Scheduling cron | [routes/console.php](routes/console.php) |
| Layout dashboard + menu laterale | [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php) |

---

## 2. Albero dei file annotato

Solo codice nostro. `vendor/`, `node_modules/`, `bootstrap/cache/`, `storage/` omessi salvo dove rilevante.

```
flamingdragon/
├── FLAMINGDRAGON.md          Identità/architettura — scritto a mano
├── USER.md                   Profilo utente — scritto a mano
├── MEMORY.md                 Guida operativa alla memoria — scritto a mano
├── WORKINGMEMORY.md          Memoria di lavoro — SOLO via WorkingMemoryService
├── TOOLS.md                  GENERATO da fd:export-registry — non editare
├── SKILLS.md                 GENERATO da fd:export-registry — non editare
├── README.md
├── codebase_reference.md     Questo file
│
├── app/
│   ├── Console/Commands/
│   │   ├── BackupCommand.php          fd:backup — snapshot in _backup/ con MANIFEST.json
│   │   ├── RestoreCommand.php         fd:restore — verifica SHA-256 prima di scrivere
│   │   ├── ExportRegistryCommand.php  fd:export-registry — genera TOOLS.md + SKILLS.md
│   │   ├── CheckIntegrityCommand.php  fd:check-integrity — SKILLS.md → TOOLS.md
│   │   ├── SyncEmbeddingsCommand.php  fd:sync-embeddings — 6 file .md → tabella memory
│   │   ├── FdWorker.php               fd:worker — wrapper su queue:work
│   │   └── FdHeartbeat.php            fd:heartbeat — cron ogni 30 min
│   │
│   ├── Enums/                         7 enum backed by string
│   │   ├── ActionType.php             tipo di riga in execution_logs
│   │   ├── AgentStatus.php            stato sessione (+ color(), isTerminal())
│   │   ├── ExecutionMode.php          sync | async | auto
│   │   ├── MemoryType.php             fact | preference | context | instruction
│   │   ├── RiskCategory.php           PERCHÉ un tool è gated (7 casi)
│   │   ├── RiskLevel.php              safe | moderate | dangerous (+ badgeColor())
│   │   └── ToolType.php               builtin | script | api | composite
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Controller.php         base astratta vuota
│   │   │   ├── Api/                   6 controller REST + webhook
│   │   │   └── Dashboard/             7 controller che rendono Blade
│   │   └── Middleware/
│   │       └── TelegramAuthMiddleware.php   secret + chat-ID allow-list
│   │
│   ├── Jobs/
│   │   └── ExecuteAgentJob.php        esecuzione async, tries=1
│   │
│   ├── Models/                        8 model Eloquent
│   │   ├── AgentSession.php  AllowedCommand.php  ExecutionLog.php
│   │   ├── LlmProvider.php   Memory.php  Skill.php  Tool.php
│   │   └── User.php                   ⚠ non usato — nessuna auth applicativa
│   │
│   ├── Providers/
│   │   └── AppServiceProvider.php     singleton EmbeddingService/EnvEditor/AIEditorService
│   │                                  + disattiva verify SSL se APP_DEBUG
│   └── Services/
│       ├── Agent/          AgentOrchestrator (loop) · AgentSpawner (sync/async)
│       │                   SessionManager (stati) · ToolExecutor (1728 righe, 58 tool)
│       ├── Command/        CommandRouter · CommandValidator · ParsedCommand (DTO)
│       ├── Dashboard/      AIEditorService (Opus) · EnvEditor
│       ├── Embeddings/     EmbeddingService (text-embedding-3-small)
│       ├── Generator/      GeneratorService (Telegram) · WebGeneratorService (dashboard)
│       ├── Llm/            LlmRouter · PromptBuilder · LlmResponse (DTO)
│       │                   LlmProviderInterface + Providers/{Anthropic,OpenAi,Ollama}
│       ├── Memory/         MemoryService (DB) · WorkingMemoryService (file .md)
│       ├── Security/       AllowListGuard · ConfirmationGate · SessionSandbox
│       ├── Skill/          SkillManager · SkillParser · SkillGenerator
│       └── Telegram/       TelegramService (Bot API) · TelegramParser (update → dati)
│
├── config/flamingdragon.php           unica config custom (le altre sono Laravel std)
│
├── database/
│   ├── migrations/                    3 Laravel + 14 FlamingDragon
│   ├── seeders/
│   │   ├── DatabaseSeeder.php         chiama Providers → Tools → Commands
│   │   ├── DefaultProvidersSeeder.php 3 provider LLM
│   │   ├── DefaultToolsSeeder.php     58 tool
│   │   ├── DefaultCommandsSeeder.php  25 comandi
│   │   └── RiskCategoryMappingSeeder.php  ⚠ NON chiamato da DatabaseSeeder
│   └── database.sqlite                ⚠ residuo — la connessione attiva è mariadb
│
├── resources/
│   ├── prompts/agent_system.md        prompt base dell'agente
│   └── views/
│       ├── layouts/app.blade.php      shell + sidebar (9 voci)
│       ├── welcome.blade.php          ⚠ default Laravel, non instradato
│       └── dashboard/                 13 view
│
├── routes/  api.php · web.php · console.php
├── skills/                            11 directory, ognuna con SKILL.md
└── tests/
    ├── Feature/ExampleTest.php        stub Laravel
    ├── Unit/ExampleTest.php           stub Laravel
    └── Unit/Services/Security/ConfirmationGateTest.php   9 test reali
```

**Peso del codice** (righe, solo file nostri): ~10.000. Il file più grande è
`ToolExecutor.php` (1728 righe), seguito da `wizard.blade.php` (826) e
`DefaultToolsSeeder.php` (571).

---

## 3. Flusso end-to-end a runtime

### 3.1 Messaggio di testo da Telegram

```
POST /api/telegram/webhook
  │
  ├─ throttle:60,1                         60 richieste/minuto
  ├─ TelegramAuthMiddleware
  │    ├─ hash_equals(secret atteso, header X-Telegram-Bot-Api-Secret-Token)
  │    │     └─ mismatch → HTTP 200 corpo vuoto (nessuna informazione trapela)
  │    └─ chat_id ∈ FD_TELEGRAM_ALLOWED_CHAT_IDS ?
  │          └─ no → HTTP 200 corpo vuoto
  │
  └─ TelegramWebhookController::handle()
       ├─ extractPhoto() ≠ null  → handlePhoto()   [§3.2]
       ├─ extractVoice() ≠ null  → handleVoice()   [§3.3]
       ├─ generator attivo?      → GeneratorService::handleMessage()
       ├─ /generateskill|tool|skilltool → GeneratorService::start()
       ├─ /confirm               → handleConfirm()
       ├─ /deny                  → handleDeny()
       ├─ testo libero (no "/")  → interpretNaturalLanguage()  → nome comando
       │
       ├─ CommandRouter::route(nome, args, NL)
       │    ├─ AllowListGuard::getCommand()  → null ⇒ "Command not recognized"
       │    ├─ resolveExecutionMode()        auto ⇒ timeout ≤ 30s ? sync : async
       │    └─ ConfirmationGate::requiresGate()
       │
       ├─ requiresConfirmation ⇒ ConfirmationGate::store() + buildPrompt() → STOP
       │
       └─ AgentSpawner::spawn()
            ├─ countRunning() ≥ max_concurrent_agents ⇒ "System is busy"
            ├─ SessionManager::create()  (status=queued)
            ├─ async ⇒ ExecuteAgentJob::dispatch() → "queued"
            └─ sync  ⇒ AgentOrchestrator::run()
```

### 3.2 Foto

`handlePhoto()` → `sendChatAction('typing')` → `downloadTelegramFile()` in
`storage/app/public/media/` → route sul comando **`vision`** → costruisce un `ParsedCommand`
**nuovo** con `requiresConfirmation: false` e `naturalLanguageInput` arricchito con il path
locale → `spawn()`.

### 3.3 Voce

`handleVoice()` → download → **Whisper chiamato direttamente nel controller**
(`transcribeWithWhisper()`, nessun agente) → `interpretNaturalLanguage(transcript)` →
route → se il comando ≠ `chat` mostra «🎤 Hai detto: …» → `spawn()`.

**Perché Whisper inline:** far girare un agente solo per trascrivere aggiungeva ~20 s di
latenza e rischiava il timeout del webhook Telegram.

### 3.4 Il loop agentico (`AgentOrchestrator::run`)

```
1. Risolve provider/model:
     definition.llm_provider_override ?? config default
     ⚠ OVERRIDE FORZATO: se commandName ∈ {generateskill, generatetool,
       generateskilltool, create_skill, write_file, run_script}
       ⇒ provider=anthropic, model=claude-opus-4-6
2. SessionManager::markRunning()
3. new SessionSandbox(uuid)->initialize()   → crea storage/app/flamingdragon/sessions/<uuid>
4. new ToolExecutor($sandbox)
5. MemoryService::getContext(query: raw_input, limit: 10)   → semantica se embedding attivi
6. SkillManager::findByName(definition.skill_required)      → SKILL.md nel prompt
7. buildToolDefinitions(tools_allowed)                      → schemi JSON per l'LLM
8. PromptBuilder::build(...)                                → system prompt completo
9. WHILE stepNumber < max_tool_calls_per_session (50):
     a. LlmRouter::chat(messages, toolDefs, {provider, model, system})
     b. logStep(ActionType::LlmCall)
     c. se NON ci sono tool call ⇒ finalAnswer = content; BREAK
     d. append messaggio assistant:
          rawBlocks normalizzati (Anthropic)  oppure  content in chiaro
     e. per ogni tool call:
          tool ∉ tools_allowed ⇒ "Tool 'x' is not granted for this session."
          altrimenti ToolExecutor::execute()
     f. TUTTI i tool_result in UN SOLO messaggio user
     g. se stopReason == 'end_turn' ⇒ BREAK
10. MemoryService::remember(namespace 'session_history', TTL 7 giorni)
11. SessionManager::markCompleted()
```

Ogni eccezione nel `while` viene catturata: log `[AgentOrchestrator] Execution failed`,
`markFailed()`, e all'utente torna la stringa generica
`"Execution failed. Please check the system logs."` — **mai** lo stack trace.

---

## 4. Schema del database

**Connessione:** `DB_CONNECTION=mariadb`, database `flamingdragon`.
17 tabelle: 8 di Laravel, 9 di FlamingDragon.

### 4.1 `agent_sessions` — una riga per esecuzione di comando

Migration: `2024_01_01_000010_create_agent_sessions_table.php`

| Colonna | Tipo | Default | Note |
|---|---|---|---|
| `id` | bigint UNSIGNED AI | — | PK |
| `session_uuid` | char(36) | — | **UNIQUE**. Chiave pubblica usata in URL e API |
| `telegram_message_id` | bigint NULL | — | messaggio che ha originato la sessione |
| `command` | varchar(255) | — | nome del comando eseguito |
| `raw_input` | text | — | input NL originale (o nome comando se assente) |
| `status` | enum | `queued` | `queued\|running\|completed\|failed\|timeout\|cancelled` |
| `execution_mode` | enum | `sync` | **solo `sync\|async`** — vedi trappola T6 |
| `agent_pid` | int NULL | — | ⚠ **mai scritto dal codice** |
| `queue_job_id` | varchar(255) NULL | — | ⚠ **mai scritto dal codice** |
| `tools_granted` | json NULL | — | cast `array` |
| `llm_provider` | varchar(50) NULL | — | scritto da `markRunning()` |
| `llm_model` | varchar(100) NULL | — | scritto da `markRunning()` |
| `tokens_input` | int | 0 | accumulato da `markCompleted()` |
| `tokens_output` | int | 0 | accumulato da `markCompleted()` |
| `result_summary` | text NULL | — | primi 500 caratteri |
| `result_full` | longtext NULL | — | risposta completa |
| `error_message` | text NULL | — | scritto da `markFailed()` |
| `started_at` | timestamp NULL | — | cast datetime |
| `completed_at` | timestamp NULL | — | cast datetime |
| `timeout_seconds` | int | 300 | copiato da `allowed_commands` |
| `created_at`/`updated_at` | timestamp | — | |

**Indici:** PK su `id`, UNIQUE su `session_uuid`. Nessun indice su `status` o `created_at`
nonostante siano le colonne più filtrate (vedi §18).

### 4.2 `allowed_commands` — l'allow-list

Migration base: `…000011`; colonne aggiunte da `…000017` e `…000019`.

| Colonna | Tipo | Default | Note |
|---|---|---|---|
| `id` | bigint AI | — | PK |
| `name` | varchar(100) | — | **UNIQUE**. È il nome usato su Telegram |
| `description` | text | — | mostrata all'LLM nel catalogo NL |
| `system_prompt` | text NULL | — | agg. da `…000017`. Iniettato dopo il prompt globale |
| `category` | varchar(50) | `general` | usata per raggruppare nel catalogo NL |
| `execution_mode` | enum | `auto` | `sync\|async\|auto` |
| `timeout_seconds` | int | 300 | con `auto`, ≤30 ⇒ sync |
| `tools_allowed` | json NULL | — | cast `array`. **È l'unica fonte dei permessi tool** |
| `llm_provider_override` | varchar(50) NULL | — | |
| `llm_model_override` | varchar(100) NULL | — | |
| `skill_required` | varchar(100) NULL | — | nome skill, non ID |
| `is_dangerous` | boolean | false | **unico flag che attiva il gate** |
| `skip_confirmation` | boolean | false | agg. da `…000019`. Bypassa il gate |
| `is_active` | boolean | true | |
| timestamps | | | |

### 4.3 `tools` — registro dei tool

Migration base: `…000013`; colonne aggiunte da `…000022` e `2026_05_05_154010`.

| Colonna | Tipo | Default | Note |
|---|---|---|---|
| `id` | bigint AI | — | PK |
| `name` | varchar(100) | — | **UNIQUE**. Deve combaciare con il `match` di `ToolExecutor::dispatch()` |
| `display_name` | varchar(255) | — | usato nel catalogo NL |
| `description` | text | — | **prevale** sulla descrizione hardcoded nel prompt |
| `type` | enum | `builtin` | `builtin\|script\|api\|composite` — solo `builtin` è implementato |
| `handler_class` | varchar(255) NULL | — | ⚠ **mai letto dal codice** |
| `config` | json NULL | — | ⚠ **mai letto dal codice** |
| `config_keys` | json NULL | — | agg. `…000022`. Chiavi `.env` mostrate in dashboard |
| `input_schema` | json NULL | — | agg. `…000022`. ⚠ **mai letto** (gli schemi sono hardcoded) |
| `risk_level` | enum | `safe` | `safe\|moderate\|dangerous` — solo display |
| `risk_category` | varchar(64) NULL | NULL | agg. `2026_05_05`. Cast a `RiskCategory` |
| `requires_confirmation` | boolean | false | ⚠ **mai letto a runtime** — vedi T9 |
| `is_active` | boolean | true | filtra l'export e il catalogo NL |
| timestamps | | | |

### 4.4 `skills`

Migration base: `…000012`; `env_required`/`tools_required` aggiunte da `…000022`.

| Colonna | Tipo | Default | Note |
|---|---|---|---|
| `id` | bigint AI | — | PK |
| `name` | varchar(100) | — | **UNIQUE**. Deve combaciare con `allowed_commands.skill_required` |
| `display_name` | varchar(255) | — | |
| `description` | text | — | |
| `version` | varchar(20) | `1.0.0` | |
| `skill_path` | varchar(500) | — | ⚠ a volte assoluto, a volte relativo — vedi T14 |
| `skill_md_path` | varchar(500) | — | path del SKILL.md. Usato da `readMarkdown()` |
| `has_scripts` | boolean | false | true se esiste `<skill>/scripts/` |
| `dependencies` | json NULL | — | scritto da `SkillManager` con i `tools_required` |
| `env_required` | json NULL | — | agg. `…000022` |
| `tools_required` | json NULL | — | agg. `…000022` |
| `is_active` | boolean | true | |
| `installed_at` | timestamp NULL | — | |
| timestamps | | | |

### 4.5 `memory` — memoria persistente

Migration base: `…000014`; `embedding`/`is_important` da `…000018`.

| Colonna | Tipo | Default | Note |
|---|---|---|---|
| `id` | bigint AI | — | PK |
| `namespace` | varchar(100) | `general` | |
| `key` | varchar(255) | — | |
| `value` | longtext | — | |
| `embedding` | json NULL | — | vettore 1536 dim di `text-embedding-3-small` |
| `is_important` | boolean | false | forza la generazione dell'embedding |
| `memory_type` | enum | `fact` | `fact\|preference\|context\|instruction` |
| `source` | varchar(255) NULL | — | es. `session:<uuid>`, `fd:sync-embeddings` |
| `expires_at` | timestamp NULL | — | NULL = mai |
| timestamps | | | |

**Indici:** PK; **UNIQUE composto `(namespace, key)`** — è la chiave di `updateOrCreate()`.

**Namespace in uso:** `general` (default dei tool `memory_*`), `session_history`
(riassunti a 7 giorni scritti dall'orchestrator), `system` (i 6 file .md di
`fd:sync-embeddings`).

### 4.6 `llm_providers`

Migration: `…000015`

| Colonna | Tipo | Default | Note |
|---|---|---|---|
| `id` | bigint AI | — | PK |
| `name` | varchar(50) | — | **UNIQUE**. `anthropic\|openai\|ollama` — il `match` di `LlmRouter` |
| `display_name` | varchar(255) | — | |
| `api_base_url` | varchar(500) | — | passato al costruttore del provider |
| `api_key_env` | varchar(100) NULL | — | **nome** della variabile, non il valore |
| `default_model` | varchar(100) | — | ⚠ scavalcato da `config` — vedi T12 |
| `available_models` | json NULL | — | |
| `is_default` | boolean | false | ⚠ **mai letto** — il default viene da config |
| `is_active` | boolean | true | filtro in `LlmRouter::getProvider()` |
| `config` | json NULL | — | ⚠ mai letto |
| timestamps | | | |

### 4.7 `execution_logs` — traccia passo-passo

Migration: `…000016`. **`public $timestamps = false`** — c'è solo `created_at`.

| Colonna | Tipo | Default | Note |
|---|---|---|---|
| `id` | bigint AI | — | PK |
| `session_id` | bigint FK | — | → `agent_sessions.id`, **ON DELETE CASCADE** |
| `step_number` | int | 0 | |
| `action_type` | enum | — | `llm_call\|tool_use\|skill_invoke\|decision\|error\|user_prompt` |
| `tool_name` | varchar(100) NULL | — | NULL per le righe `llm_call` |
| `input_data` | longtext NULL | — | |
| `output_data` | longtext NULL | — | troncato a 65535 caratteri dall'orchestrator |
| `tokens_used` | int | 0 | 0 per le righe `tool_use` |
| `duration_ms` | int | 0 | |
| `created_at` | timestamp | CURRENT | |

⚠ `skill_invoke`, `decision` e `user_prompt` esistono nell'enum ma **nessun codice li scrive**.

### 4.8 `fd_todos`

Migration: `…000020`. **Accesso solo via query builder** (`DB::table`), nessun model Eloquent.

| Colonna | Tipo | Default |
|---|---|---|
| `id` | bigint AI | — |
| `title` | varchar(255) | — |
| `notes` | text NULL | — |
| `list_name` | varchar(255) | `default` |
| `priority` | varchar(255) | `normal` (valori attesi: `low\|normal\|high`, **non vincolati dal DB**) |
| `is_done` | boolean | false |
| `due_at` | timestamp NULL | — |
| `done_at` | timestamp NULL | — |
| timestamps | | |

### 4.9 `fd_shopping_items`

Migration: `…000021`. Anche qui solo query builder.

| Colonna | Tipo | Default |
|---|---|---|
| `id` | bigint AI | — |
| `name` | varchar(255) | — |
| `list_name` | varchar(255) | `default` |
| `category` | varchar(255) NULL | — |
| `quantity` | decimal(8,2) | 1 |
| `unit` | varchar(255) NULL | — |
| `is_bought` | boolean | false |
| `bought_at` | timestamp NULL | — |
| timestamps | | |

### 4.10 Tabelle Laravel standard

`users`, `password_reset_tokens`, `sessions` (`0001_01_01_000000`); `cache`, `cache_locks`
(`…000001`); `jobs`, `job_batches`, `failed_jobs` (`…000002`).
`jobs` è usata davvero (queue driver = database). `users` **non è usata**: non c'è
autenticazione applicativa.

---

## 5. Enums

Tutti in `App\Enums`, tutti `enum X: string`.

### `ActionType` — [ActionType.php](app/Enums/ActionType.php)
| Case | Valore | `label()` |
|---|---|---|
| `LlmCall` | `llm_call` | Large Language Model Call |
| `ToolUse` | `tool_use` | Tool Use |
| `SkillInvoke` | `skill_invoke` | Skill Invocation |
| `Decision` | `decision` | Decision |
| `Error` | `error` | Error |
| `UserPrompt` | `user_prompt` | User Prompt |

### `AgentStatus` — [AgentStatus.php](app/Enums/AgentStatus.php)
| Case | Valore | `label()` | `color()` | terminale |
|---|---|---|---|---|
| `Queued` | `queued` | Queued | yellow | no |
| `Running` | `running` | Running | blue | no |
| `Completed` | `completed` | Completed | green | **sì** |
| `Failed` | `failed` | Failed | red | **sì** |
| `Timeout` | `timeout` | Timed Out | orange | **sì** |
| `Cancelled` | `cancelled` | Cancelled | gray | **sì** |

Metodi: `label(): string` · `color(): string` · `isTerminal(): bool`

### `ExecutionMode` — `Sync='sync'` · `Async='async'` · `Auto='auto'`. Metodo `label(): string`.

### `MemoryType` — `Fact='fact'` · `Preference='preference'` · `Context='context'` · `Instruction='instruction'`. Metodo `label(): string`.

### `RiskLevel` — `Safe='safe'` · `Moderate='moderate'` · `Dangerous='dangerous'`. Metodi `label(): string` · `badgeColor(): string` (green/yellow/red).

### `ToolType` — `Builtin='builtin'` · `Script='script'` · `Api='api'` · `Composite='composite'`. Metodo `label(): string`. **Solo `builtin` è implementato.**

### `RiskCategory` — [RiskCategory.php](app/Enums/RiskCategory.php)
Spiega **perché** un tool è pericoloso. Usata solo per l'etichetta nel prompt di conferma.

| Case | Valore | `label()` (IT) | Significato |
|---|---|---|---|
| `Bash` | `bash` | Esecuzione shell | shell, artisan, composer, npm |
| `FileDelete` | `file_delete` | Eliminazione file | cancellazione file/mail/eventi |
| `DatabaseDestructive` | `db_destructive` | Operazione DB distruttiva | DROP/TRUNCATE/DELETE |
| `GitPush` | `git_push` | Push git | push, force-push, rebase |
| `MessageThirdParty` | `message_third_party` | Messaggio a terzi | email, WhatsApp, social, Telegram |
| `FirewallChange` | `firewall` | Modifica firewall | **riservata — nessun tool la usa** |
| `SystemFileMd` | `system_md` | Modifica file di sistema | **riservata — nessun tool la usa** |

---

## 6. Models

### `AgentSession` — [app/Models/AgentSession.php](app/Models/AgentSession.php)

`$fillable`: session_uuid, telegram_message_id, command, raw_input, status, execution_mode,
agent_pid, queue_job_id, tools_granted, llm_provider, llm_model, tokens_input, tokens_output,
result_summary, result_full, error_message, started_at, completed_at, timeout_seconds.

`$casts`: `status→AgentStatus`, `execution_mode→ExecutionMode`, `tools_granted→array`,
`started_at→datetime`, `completed_at→datetime`.

| Metodo | Firma | Effetto |
|---|---|---|
| `logs` | `logs(): HasMany` | `hasMany(ExecutionLog::class, 'session_id')` |
| `totalTokens` | `totalTokens(): int` | `tokens_input + tokens_output` |
| `durationSeconds` | `durationSeconds(): ?int` | null se `started_at` o `completed_at` è null |
| `isRunning` | `isRunning(): bool` | `status === AgentStatus::Running` |
| `isTerminal` | `isTerminal(): bool` | delega a `status->isTerminal()` |

### `AllowedCommand` — [app/Models/AllowedCommand.php](app/Models/AllowedCommand.php)

`$fillable`: name, description, category, execution_mode, timeout_seconds, tools_allowed,
llm_provider_override, llm_model_override, skill_required, system_prompt, is_dangerous,
skip_confirmation, is_active.

`$casts`: `execution_mode→ExecutionMode`, `tools_allowed→array`, `is_dangerous→bool`,
`skip_confirmation→bool`, `is_active→bool`.

| Metodo | Firma | Effetto |
|---|---|---|
| `resolvedExecutionMode` | `resolvedExecutionMode(): ExecutionMode` | `Auto ⇒ Sync`, altrimenti se stesso. ⚠ **Non usato da nessuno**: il router applica la sua logica basata sul timeout |

### `ExecutionLog` — [app/Models/ExecutionLog.php](app/Models/ExecutionLog.php)

`public $timestamps = false`. `$fillable`: session_id, step_number, action_type, tool_name,
input_data, output_data, tokens_used, duration_ms, created_at.
`$casts`: `action_type→ActionType`, `created_at→datetime`.

| Metodo | Firma | Effetto |
|---|---|---|
| `session` | `session(): BelongsTo` | `belongsTo(AgentSession::class, 'session_id')` |

### `LlmProvider` — [app/Models/LlmProvider.php](app/Models/LlmProvider.php)

`protected $table = 'llm_providers'`.
`$casts`: `available_models→array`, `is_default→bool`, `is_active→bool`, `config→array`.

| Metodo | Firma | Effetto |
|---|---|---|
| `resolveApiKey` | `resolveApiKey(): ?string` | `env($this->api_key_env)`; null se `api_key_env` vuoto |

### `Memory` — [app/Models/Memory.php](app/Models/Memory.php)

`protected $table = 'memory'`.
`$casts`: `memory_type→MemoryType`, `expires_at→datetime`, `embedding→array`, `is_important→bool`.

| Metodo | Firma | Effetto |
|---|---|---|
| `isExpired` | `isExpired(): bool` | `expires_at !== null && expires_at->isPast()` |

### `Skill` — [app/Models/Skill.php](app/Models/Skill.php)

`$casts`: `dependencies→array`, `env_required→array`, `tools_required→array`,
`has_scripts→bool`, `is_active→bool`, `installed_at→datetime`.

| Metodo | Firma | Effetto |
|---|---|---|
| `readMarkdown` | `readMarkdown(): ?string` | legge `skill_md_path`; null se manca |
| `parseFrontmatter` | `parseFrontmatter(): array` | parser YAML minimale: scalari + array JSON `[…]`. Ritorna `[]` se il file non inizia con `---` |
| `getEnvRequiredList` | `getEnvRequiredList(): array` | colonna DB, altrimenti fallback su frontmatter |
| `getToolsRequiredList` | `getToolsRequiredList(): array` | idem |

### `Tool` — [app/Models/Tool.php](app/Models/Tool.php)

`$casts`: `type→ToolType`, `risk_level→RiskLevel`, `risk_category→RiskCategory`,
`config→array`, `config_keys→array`, `input_schema→array`,
`requires_confirmation→bool`, `is_active→bool`.

| Metodo | Firma | Effetto |
|---|---|---|
| `executorMethod` | `executorMethod(): string` | `'run' . str_replace('_','', ucwords($name,'_'))`. Es. `gmail_send → runGmailSend`. **Base dell'editor AI dei tool** |

### `User` — model Laravel di default. **Non usato**: nessuna rotta autenticata.

---

## 7. Services

### 7.1 `App\Services\Agent`

#### `AgentOrchestrator` — [AgentOrchestrator.php](app/Services/Agent/AgentOrchestrator.php)

**Costruttore (DI):** `LlmRouter $llmRouter`, `PromptBuilder $promptBuilder`,
`MemoryService $memoryService`, `SkillManager $skillManager`, `SessionManager $sessions`,
`TelegramService $telegram` — tutti `private readonly`.

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `run` | `run(AgentSession $session, ParsedCommand $command, ?int $chatId = null): string` | Il loop agentico. Ritorna la risposta finale, o la stringa generica di errore. Vedi §3.4 |
| `normalizeRawBlocks` | `private normalizeRawBlocks(array $blocks): array` | Nei blocchi `tool_use`, converte `input` vuoto (`[]`) in `new \stdClass()` così `json_encode` produce `{}` |
| `buildToolDefinitions` | `private buildToolDefinitions(array $grantedTools): array` | Mappa hardcoded nome→{description, properties} per **56 tool**. Filtra sui `grantedTools`. `properties` vuoto ⇒ `stdClass` |
| `logStep` | `private logStep(int $sessionId, int $stepNumber, ActionType $actionType, ?string $input, ?string $output, int $tokens = 0, int $duration = 0): void` | Scrive in `execution_logs`, `tool_name` sempre null. Output troncato a 65535. Fallisce in silenzio (log). |

⚠ `$chatId` è nella firma di `run()` ma **non viene mai usato** nel corpo, e `$telegram`
è iniettato ma mai chiamato: entrambi residui di un'idea di messaggi di progresso.

#### `AgentSpawner` — [AgentSpawner.php](app/Services/Agent/AgentSpawner.php)

**Costruttore:** `AgentOrchestrator $orchestrator`, `SessionManager $sessions`.

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `spawn` | `spawn(ParsedCommand $command, ?int $telegramMessageId = null, ?int $telegramChatId = null): string` | Verifica `max_concurrent_agents`, crea la sessione, instrada sync/async |
| `spawnSync` | `private spawnSync(AgentSession $s, ParsedCommand $c, ?int $chatId): string` | try/catch → `markFailed` + "Execution failed. Check the logs for details." |
| `spawnAsync` | `private spawnAsync(AgentSession $s, ParsedCommand $c, ?int $chatId): string` | `ExecuteAgentJob::dispatch(...)->onQueue('default')->timeout(...)`. Ritorna `"Command 'x' queued. Session: \`uuid\`"` |

#### `SessionManager` — [SessionManager.php](app/Services/Agent/SessionManager.php)

Nessuna dipendenza iniettata.

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `create` | `create(ParsedCommand $command, ?int $telegramMessageId = null): AgentSession` | UUID v4, status `queued`, **`Auto` mappato a `Sync`** (l'enum DB non ha `auto`) |
| `markRunning` | `markRunning(AgentSession $session, string $llmProvider, string $llmModel): void` | status `running` + `started_at = now()` |
| `markCompleted` | `markCompleted(AgentSession $session, string $resultSummary, string $resultFull, int $tokensIn = 0, int $tokensOut = 0): void` | **somma** i token a quelli esistenti |
| `markFailed` | `markFailed(AgentSession $session, string $errorMessage): void` | status `failed` + `completed_at` |
| `markCancelled` | `markCancelled(AgentSession $session): void` | status `cancelled` |
| `markTimeout` | `markTimeout(AgentSession $session): void` | status `timeout`. Usato solo da `FdHeartbeat` (che però scrive direttamente con `update()`) |
| `countRunning` | `countRunning(): int` | conta le sessioni `running` |
| `findByUuid` | `findByUuid(string $uuid): ?AgentSession` | ⚠ **mai chiamato** |

#### `ToolExecutor` — [ToolExecutor.php](app/Services/Agent/ToolExecutor.php)

**Costruttore:** `SessionSandbox $sandbox` (private readonly). **Non è un singleton**:
l'orchestrator ne crea uno nuovo per sessione.

| Metodo pubblico | Firma completa | Cosa fa |
|---|---|---|
| `execute` | `execute(string $toolName, array $arguments, int $sessionId, int $stepNumber): array` | Ritorna `['output' => string, 'success' => bool]`. **Non lancia mai**: cattura tutto e mette l'errore in `output`. Chiama `sandbox->recordToolCall()` prima del dispatch |

| Metodo privato | Firma | Cosa fa |
|---|---|---|
| `dispatch` | `dispatch(string $toolName, array $args): string` | `match` su 58 nomi; default ⇒ `RuntimeException("Unknown tool: …")` |
| `logStep` | `logStep(int $sessionId, int $stepNumber, string $toolName, ?string $input, ?string $output, int $durationMs, bool $isError = false): void` | `action_type` = `error` se `$isError`, altrimenti `tool_use` |
| `base64urlDecode` / `base64urlEncode` | `(string $data): string` | base64 URL-safe per Gmail |
| `extractGmailBody` | `(array $payload): string` | ricorsivo su `multipart/*`; fallback `text/html` con `strip_tags` |
| `gmailHeader` | `(array $headers, string $name): string` | ricerca case-insensitive |
| `googleOAuthToken` | `(string $refreshToken, string $context = 'Google'): string` | refresh token → access token |
| `googleAccessToken` | `(): string` | usa `GOOGLE_REFRESH_TOKEN` (scope calendar) |
| `gmailAccessToken` | `(): string` | usa `GOOGLE_GMAIL_REFRESH_TOKEN`, fallback su `GOOGLE_REFRESH_TOKEN` |
| `ensureGeneratedDir` | `(): string` | crea/ritorna `storage/app/public/generated` |
| `run*` (58) | `(array $args): string` | un metodo per tool — vedi §11 |

**Convenzione degli argomenti mancanti:**
`$x = $args['x'] ?? throw new RuntimeException('tool: missing required argument "x".');`

### 7.2 `App\Services\Command`

#### `ParsedCommand` (DTO `final`) — [ParsedCommand.php](app/Services/Command/ParsedCommand.php)

Costruttore con proprietà promosse `public readonly`:
```php
__construct(
    string         $commandName,
    array          $arguments,
    AllowedCommand $definition,
    bool           $requiresConfirmation,
    ExecutionMode  $executionMode,
    ?string        $naturalLanguageInput = null,
)
```

#### `CommandRouter` — [CommandRouter.php](app/Services/Command/CommandRouter.php)

**Costruttore:** `AllowListGuard $guard`, `ConfirmationGate $gate`.

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `route` | `route(string $commandName, array $arguments = [], ?string $naturalLanguage = null): ?ParsedCommand` | null se il comando non è in allow-list (logga `[CommandRouter] Command not found`) |
| `resolveExecutionMode` | `private resolveExecutionMode(AllowedCommand $command): ExecutionMode` | `Auto` ⇒ `timeout_seconds <= sync_threshold (30)` ? `Sync` : `Async` |
| `listCommands` | `listCommands(?string $category = null)` | **nessun tipo di ritorno dichiarato**. Ritorna una Collection di `AllowedCommand` attivi ordinati per categoria+nome. ⚠ mai chiamato |

#### `CommandValidator` — [CommandValidator.php](app/Services/Command/CommandValidator.php)

**Costruttore:** `AllowListGuard $guard`.

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `validate` | `validate(ParsedCommand $command): array` | Ritorna `['valid' => bool, 'errors' => string[]]` |

⚠ **Questa classe non è mai istanziata da nessuna parte.** Vedi §18.

### 7.3 `App\Services\Security`

#### `AllowListGuard` — [AllowListGuard.php](app/Services/Security/AllowListGuard.php)

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `isAllowed` | `isAllowed(string $commandName): bool` | **Se `security.allow_list_strict` è false ritorna sempre true.** Altrimenti cerca il comando attivo |
| `getCommand` | `getCommand(string $commandName): ?AllowedCommand` | Prima riga con `name` e `is_active=true`. ⚠ **Non rispetta `allow_list_strict`** |
| `toolsAreGranted` | `toolsAreGranted(array $requestedTools, array $allowedTools): bool` | ⚠ mai chiamato: l'orchestrator fa il controllo inline con `in_array` |

#### `ConfirmationGate` — [ConfirmationGate.php](app/Services/Security/ConfirmationGate.php)

Costanti: `CACHE_KEY = 'fd_pending_command:%d'`, `CACHE_TTL = 300` (5 minuti).
Nessuna dipendenza iniettata (usa la facade `Cache`).

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `requiresGate` | `requiresGate(AllowedCommand $command): bool` | `false` se `!is_dangerous`; `false` se `skip_confirmation`; altrimenti il valore di `security.dangerous_commands_require_confirmation` |
| `store` | `store(int $chatId, ParsedCommand $cmd): void` | Salva `['command' => nome, 'args' => arguments]` in cache per 5 min. **Sovrascrive** l'eventuale precedente |
| `retrieve` | `retrieve(int $chatId): ?array` | `['command' => string, 'args' => array]` o null |
| `clear` | `clear(int $chatId): void` | `Cache::forget()`. Idempotente |
| `buildPrompt` | `buildPrompt(AllowedCommand $command): string` | HTML per Telegram: «⚠️ Comando pericoloso: `<code>nome</code>` [+ Categoria di rischio] … /confirm … /deny». Nome passato in `htmlspecialchars(..., ENT_XML1)` |
| `cacheKey` | `private cacheKey(int $chatId): string` | |
| `riskCategoryLabel` | `private riskCategoryLabel(AllowedCommand $command): ?string` | Cerca **il primo** tool in `tools_allowed` con `risk_category` non null e ne ritorna la `label()` |

#### `SessionSandbox` — [SessionSandbox.php](app/Services/Security/SessionSandbox.php)

**Costruttore:** `__construct(private readonly string $sessionUuid)`. Nel corpo legge
`security.max_tool_calls_per_session` e `execution.session_dir`; `sessionDir = baseDir/uuid`.

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `initialize` | `initialize(): void` | `mkdir($sessionDir, 0755, true)` se non esiste |
| `recordToolCall` | `recordToolCall(): void` | Incrementa; **lancia `RuntimeException`** se supera il massimo |
| `getToolCallCount` | `getToolCallCount(): int` | |
| `validatePath` | `validatePath(string $path): void` | 1) `realpath()`; 2) blocca se inizia per un `blocked_paths`; 3) **se `allowed_base_paths` è vuoto esce senza controlli**; 4) consente se dentro un `allowed_base_paths` o dentro `sessionDir`; 5) altrimenti lancia. Vedi trappola T2 |
| `getSessionDir` | `getSessionDir(): string` | |
| `cleanup` | `cleanup(): void` | Rimuove ricorsivamente la dir. ⚠ **mai chiamato** (la pulizia la fa `fd:heartbeat` per età) |
| `removeDirectory` | `private removeDirectory(string $dir): void` | |

### 7.4 `App\Services\Llm`

#### `LlmProviderInterface` — [LlmProviderInterface.php](app/Services/Llm/LlmProviderInterface.php)
```php
public function chat(array $messages, array $tools = [], array $options = []): LlmResponse;
public function getAvailableModels(): array;
public function countTokens(string $text): int;
```

#### `LlmResponse` (DTO `final`) — [LlmResponse.php](app/Services/Llm/LlmResponse.php)
```php
__construct(
    public readonly string $content,       // testo concatenato dei blocchi text
    public readonly array  $toolCalls,     // [{id, name, arguments}]
    public readonly int    $inputTokens,
    public readonly int    $outputTokens,
    public readonly string $stopReason,    // 'end_turn' | 'tool_use' | 'max_tokens'
    public readonly array  $rawBlocks = [],// blocchi grezzi Anthropic
)
```
| Metodo | Firma | Effetto |
|---|---|---|
| `hasToolCalls` | `(): bool` | `!empty($toolCalls)` |
| `isComplete` | `(): bool` | `stopReason === 'end_turn'` |
| `totalTokens` | `(): int` | somma |

#### `LlmRouter` — [LlmRouter.php](app/Services/Llm/LlmRouter.php)

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `getProvider` | `getProvider(?string $providerName = null, ?string $model = null): LlmProviderInterface` | Default da config. Provider non trovato ⇒ log warning + **fallback su `anthropic`**. Nessun provider attivo ⇒ `RuntimeException('No active Large Language Model provider found.')`. Vedi T12 |
| `chat` | `chat(array $messages, array $tools = [], array $options = []): LlmResponse` | Legge `$options['provider']` e `$options['model']`, poi delega |

#### `PromptBuilder` — [PromptBuilder.php](app/Services/Llm/PromptBuilder.php)

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `build` | `build(AllowedCommand $command, array $grantedTools, array $memories, ?Skill $skill, int $maxToolCalls, int $timeoutSeconds, array $toolDefinitions = []): string` | Concatena con `\n\n` (filtrando i vuoti): base + comando + memorie + skill + tool + vincoli |
| `buildCapabilityCatalog` | `buildCapabilityCatalog(): string` | Catalogo compatto dei comandi. ⚠ **mai chiamato** — il webhook usa il proprio `buildNlCatalog()` |
| `loadBasePrompt` | `private (): string` | Legge `llm.system_prompt_path`; se manca logga e usa un fallback inline di 5 righe |
| `buildMemoryContext` | `private (array $memories): string` | `## Relevant Memories` + `- [ns/key]: value` |
| `buildSkillContext` | `private (?Skill $skill): string` | `## Active Skill: <display_name>` + tutto il SKILL.md |
| `buildToolContext` | `private (array $toolNames, array $toolDefinitions): string` | Per ogni tool: `### \`nome\``, descrizione **dal DB** (fallback su quella dello schema), elenco parametri con tipo e `*(required)*`/`*(optional)*` |
| `buildConstraints` | `private (AllowedCommand $command, int $maxToolCalls, int $timeoutSeconds): string` | `## Session Constraints` |

⚠ `buildToolContext` legge `input_schema['required']`, ma `buildToolDefinitions()`
**non emette mai** la chiave `required`: nel prompt ogni parametro risulta `*(optional)*`.

#### `AnthropicProvider` — [AnthropicProvider.php](app/Services/Llm/Providers/AnthropicProvider.php)

`__construct(string $apiKey, string $model = 'claude-sonnet-4-20250514', string $apiBase = 'https://api.anthropic.com/v1')`

- `chat()`: POST `{apiBase}/messages`, header `x-api-key` + `anthropic-version: 2023-06-01`,
  timeout 120 s. `max_tokens` da `$options` o `llm.max_tokens` (4096). `system` e `tools`
  aggiunti solo se non vuoti. Separa blocchi `text` (concatenati in `content`) e `tool_use`
  (in `toolCalls`), e conserva **tutti** i blocchi in `rawBlocks`.
  Risposta non 2xx ⇒ `RuntimeException("Anthropic API error {status}: {body}")`.
- `getAvailableModels(): array` → `['claude-opus-4-20250514','claude-sonnet-4-20250514','claude-haiku-4-5-20251001']`
- `countTokens(string $text): int` → `ceil(mb_strlen/4)` (stima)
- `formatMessages(array $messages): array` (private) → tiene solo i ruoli `user`/`assistant`

#### `OpenAiProvider` — [OpenAiProvider.php](app/Services/Llm/Providers/OpenAiProvider.php)

`__construct(string $apiKey, string $model = 'gpt-4o', string $apiBase = 'https://api.openai.com/v1')`

`chat()` converte gli schemi Anthropic in formato OpenAI
(`{type:'function', function:{name, description, parameters}}`) e mappa
`finish_reason`: `tool_calls→tool_use`, `length→max_tokens`, altro `→end_turn`.
**Non popola `rawBlocks`.** Modelli: `gpt-4o`, `gpt-4o-mini`, `gpt-4-turbo`.

#### `OllamaProvider` — [OllamaProvider.php](app/Services/Llm/Providers/OllamaProvider.php)

`__construct(string $model = 'llama3', string $apiBase = 'http://localhost:11434')`.
POST `{apiBase}/api/chat`, `stream:false`, timeout 300 s.
**Ignora completamente `$tools` e ritorna sempre `toolCalls: []` e `stopReason: 'end_turn'`**:
non è utilizzabile per il loop agentico, solo per risposte in un colpo solo.
`getAvailableModels()` interroga `/api/tags`, con fallback `['llama3','mistral','phi3']`.

### 7.5 `App\Services\Memory`

#### `MemoryService` — [MemoryService.php](app/Services/Memory/MemoryService.php)

**Costruttore:** `EmbeddingService $embeddings`.
Costante: `AUTO_EMBED_THRESHOLD = 150` caratteri.

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `remember` | `remember(string $namespace, string $key, string $value, MemoryType $type = MemoryType::Fact, ?string $source = null, ?int $expiresInSeconds = null, bool $important = false): Memory` | `updateOrCreate` su `(namespace,key)`. Genera l'embedding se `important` **o** `strlen ≥ 150` **e** il servizio è disponibile. Il fallimento dell'embedding è solo un warning |
| `recall` | `recall(string $namespace, string $key): ?Memory` | null anche se scaduta |
| `search` | `search(string $query, ?string $namespace = null, int $limit = 20): Collection` | `LIKE %query%` su `value`, esclude le scadute, ordina per `updated_at` desc |
| `semanticSearch` | `semanticSearch(string $query, int $limit = 10): array` | Se non ci sono embedding ⇒ fallback su `search()`. Carica **tutte** le memorie con embedding, calcola la cosine similarity **in PHP**, ordina, prende `$limit` |
| `getContext` | `getContext(string $query = '', ?string $namespace = null, int $limit = 10): array` | Semantica se `$query` non vuota e servizio disponibile; altrimenti le più recenti non scadute |
| `forget` | `forget(string $namespace, string $key): bool` | |
| `sweep` | `sweep(): int` | Cancella le memorie con `expires_at <= now()`. Usata da `fd:heartbeat` |
| `backfillEmbeddings` | `backfillEmbeddings(): array` | Ritorna `['generated'=>int,'skipped'=>int,'failed'=>int]`. Seleziona `embedding IS NULL` con `is_important` **o** `CHAR_LENGTH(value) >= 150` (⚠ SQL MySQL-specifico) |

#### `WorkingMemoryService` — [WorkingMemoryService.php](app/Services/Memory/WorkingMemoryService.php)

Costanti: `MAX_TOKENS = 10_000`, `TARGET_TOKENS = 9_000`.
`__construct()` senza argomenti: fissa `$this->path = base_path('WORKINGMEMORY.md')`.

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `append` | `append(string $line): void` | Prefissa `[<ISO8601 UTC>] `, scrive in append sotto **`flock(LOCK_EX)`**, rilegge tutto e tronca se supera `MAX_TOKENS`. Se `fopen` fallisce esce in silenzio |
| `read` | `read(?int $lastLines = null): string` | File intero, o le ultime N righe. `''` se il file non esiste |
| `estimateTokens` | `private (string $text): int` | `ceil(mb_strlen/4)` — euristica GPT-4, **non** tiktoken |
| `truncate` | `private (string $content): string` | Preserva l'header (righe iniziali che iniziano per `#`, `>`, `<!--` o vuote) e rimuove le righe **più vecchie** finché non rientra in `TARGET_TOKENS` |

### 7.6 `App\Services\Embeddings\EmbeddingService` — [EmbeddingService.php](app/Services/Embeddings/EmbeddingService.php)

Costanti: `MODEL = 'text-embedding-3-small'`, `MAX_CHARS = 8000`.
`__construct(private readonly string $apiKey = '')` — registrato come **singleton** in
`AppServiceProvider` con `env('OPENAI_API_KEY','')`.

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `generate` | `generate(string $text): ?array` | null se manca la chiave o il testo è vuoto. Tronca a 8000 caratteri. POST `https://api.openai.com/v1/embeddings`, timeout 15 s. **Non lancia mai**: errori → warning + null |
| `cosineSimilarity` | `cosineSimilarity(array $a, array $b): float` | Itera su `min(count(a),count(b))`. Ritorna 0.0 se un vettore è nullo |
| `isAvailable` | `isAvailable(): bool` | `!empty($apiKey)` |

### 7.7 `App\Services\Skill`

#### `SkillManager` — **Costruttore:** `SkillParser $parser`.

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `install` | `install(string $sourcePath): Skill` | Richiede `<source>/SKILL.md` (altrimenti `RuntimeException`). Copia in `skills/<name>` se diversa dalla sorgente, rileva `scripts/`, `updateOrCreate` su `name` |
| `uninstall` | `uninstall(int $skillId): void` | Soft: `is_active = false`. **Non cancella i file** |
| `toggle` | `toggle(int $skillId): bool` | Inverte `is_active` e ritorna il nuovo stato |
| `findByName` | `findByName(string $name): ?Skill` | Solo skill attive |
| `copyDirectory` | `private (string $src, string $dst): void` | Copia ricorsiva |

#### `SkillParser`

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `parse` | `parse(string $filePath): array` | `RuntimeException` se il file manca o è illeggibile |
| `parseContent` | `parseContent(string $content): array` | Ritorna `['name','description','version','tools_required','body']`. Regex `/^---\s*\n(.*?)\n---\s*\n(.*)/s` |
| `parseYamlSimple` | `private (string $yaml): array` | `chiave: valore`, trim di spazi/virgolette |
| `parseList` | `private (string\|array $raw): array` | Riconosce `["a","b"]`; altrimenti stringa singola |

⚠ `parseContent` **non estrae `env_required`**: quel campo lo leggono solo
`Skill::parseFrontmatter()` e `ExportRegistryCommand::parseFrontmatter()`.

#### `SkillGenerator` — **Costruttore:** `LlmRouter $llmRouter`, `SkillManager $skillManager`.

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `generate` | `generate(string $description): array` | Scrive `SKILL.md` in `skills.workspace/<nome>`. Ritorna `['skill_md'=>string,'workspace_path'=>string]` |
| `approve` | `approve(string $workspacePath): \App\Models\Skill` | Delega a `SkillManager::install()` |
| `deriveSkillName` | `private (string $description): string` | Prime 3 parole, minuscole, unite da `-` |
| `buildGenerationPrompt` | `private (string $description, string $skillName): string` | |
| `extractSkillMd` | `private (string $llmOutput): string` | Toglie i fence markdown |

⚠ **`SkillGenerator` non è mai istanziata**: la generazione passa da `GeneratorService`
(Telegram) o `WebGeneratorService` (dashboard).

### 7.8 `App\Services\Telegram`

#### `TelegramService` — [TelegramService.php](app/Services/Telegram/TelegramService.php)

`__construct()` senza argomenti: costruisce `apiBase = "https://api.telegram.org/bot{token}"`
leggendo `flamingdragon.telegram.bot_token`.
⚠ Il token è letto **una sola volta alla costruzione**: se `.env` cambia a runtime
(wizard) l'istanza già risolta resta con il token vecchio.

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `sendMessage` | `sendMessage(int\|string $chatId, string $text, string $parseMode = 'HTML'): bool` | timeout 10 s. Ritorna false e logga in caso di errore |
| `sendLongMessage` | `sendLongMessage(int\|string $chatId, string $text): void` | `str_split($text, 4000)` e invii successivi (limite Telegram: 4096) |
| `setWebhook` | `setWebhook(string $url, string $secret = ''): array` | Passa `secret_token` solo se non vuoto |
| `getWebhookInfo` | `getWebhookInfo(): array` | |
| `getMe` | `getMe(): array` | |
| `sendChatAction` | `sendChatAction(int\|string $chatId, string $action = 'typing'): void` | timeout 5 s, eccezioni ignorate (non critico) |
| `downloadTelegramFile` | `downloadTelegramFile(string $fileId): string` | `getFile` → download → salva in `storage/app/public/media/tg_<…>_<ts>.<ext>`. Ritorna il path assoluto. `RuntimeException` se manca `file_path` |
| `publicMediaUrl` | `publicMediaUrl(string $localPath): string` | `url("storage/media/<basename>")` |
| `sendPhoto` | `sendPhoto(int\|string $chatId, string $photo, string $caption = ''): bool` | Se `file_exists($photo)` → multipart, altrimenti URL/file_id |
| `sendVoice` | `sendVoice(int\|string $chatId, string $audioPath, string $caption = ''): bool` | multipart `voice` (OGG/OPUS), timeout 60 s |
| `sendAudio` | `sendAudio(int\|string $chatId, string $audioPath, string $title = '', string $caption = ''): bool` | multipart `audio` (MP3) |

#### `TelegramParser` — [TelegramParser.php](app/Services/Telegram/TelegramParser.php)

Classe pura, senza stato. Legge sia `message` che `callback_query`.

| Metodo | Firma completa | Ritorno |
|---|---|---|
| `extractChatId` | `(array $update): ?int` | `message.chat.id` ?? `callback_query.message.chat.id` |
| `extractMessageId` | `(array $update): ?int` | idem su `message_id` |
| `extractText` | `(array $update): ?string` | `message.text` ?? `callback_query.data` |
| `parseCommand` | `(string $text): array` | `['command'=>?string,'args'=>string[],'natural_language'=>?string]`. Con `/`: minuscolo, rimuove `@BotName`, split su spazi. Senza `/`: `command=null`, testo in `natural_language` |
| `extractPhoto` | `(array $update): ?array` | `['file_id','width','height']` della **più grande** (ultima dell'array) |
| `extractVoice` | `(array $update): ?array` | `['file_id','duration','mime_type']` |
| `extractCaption` | `(array $update): ?string` | `message.caption` |
| `isMessage` | `(array $update): bool` | ⚠ mai chiamato |

### 7.9 `App\Services\Generator`

#### `GeneratorService` (flusso Telegram multi-turno) — [GeneratorService.php](app/Services/Generator/GeneratorService.php)

**Costruttore:** `TelegramService $telegram`, `LlmRouter $llmRouter`.
Stato in cache sotto `fd_gen:{chatId}`, TTL **900 s** (15 min).
Struttura: `{type, step, name, description, tools}`.
Macchina a stati: `ask_name → ask_description → ask_tools → ask_confirm → generating`.
`/cancel` in qualsiasi momento azzera lo stato.

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `isActive` | `isActive(int $chatId): bool` | `Cache::has()` |
| `start` | `start(int $chatId, string $type): void` | `$type ∈ {skill, tool, skilltool}` |
| `handleMessage` | `handleMessage(int $chatId, string $text): void` | Dispatch sullo step. Su eccezione: azzera lo stato e manda l'errore |
| `handleName` | `private (int $chatId, array $state, string $text): void` | Normalizza in `[a-z0-9_]`, min 2 caratteri |
| `handleDescription` | `private (…): void` | Min 10 caratteri |
| `handleTools` | `private (…): void` | `none` ⇒ array vuoto |
| `handleConfirm` | `private (…): void` | Accetta `yes\|y\|si\|sì\|ok` |
| `generate` | `private (int $chatId, array $state): void` | `finally { Cache::forget() }` |
| `generateSkill` | `private (int $chatId, string $name, string $description, array $tools): void` | Scrive `skills/<name>/SKILL.md` e registra la skill **attiva** |
| `generateTool` | `private (int $chatId, string $name, string $description): void` | **Non scrive codice**: registra il tool come `is_active=false` e manda il PHP su Telegram (max 2000 caratteri) perché lo incolli a mano |

#### `WebGeneratorService` (flusso dashboard, one-shot) — [WebGeneratorService.php](app/Services/Generator/WebGeneratorService.php)

**Costruttore:** `LlmRouter $llm`. Costanti: `MODEL = 'claude-opus-4-6'`, `PROVIDER = 'anthropic'`.

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `generate` | `generate(string $type, string $name, string $description, array $tools = [], string $extraPrompt = ''): array` | Ritorna `['skill'?, 'tool'?, 'command'?, 'errors' => string[]]`. Ogni fase è in try/catch indipendente |
| `generateSkill` | `private (string $name, string $description, array $tools, string $extraPrompt): array` | `['id','name','path','content']`. Ricarica `env_required`/`tools_required` dal frontmatter |
| `generateTool` | `private (string $name, string $description, string $extraPrompt): array` | `['id','name','code']`. **Scrive davvero dentro `ToolExecutor.php`.** Verifica che la risposta contenga `function run<Nome>`, altrimenti `RuntimeException` |
| `registerCommand` | `private (string $name, string $description, string $type, array $tools): array` | Crea/aggiorna un `AllowedCommand` categoria `personal`, `sync`, timeout 60, `is_dangerous=false` |
| `insertToolMethod` | `private (string $methodCode): void` | Inserisce prima del marcatore `    // ====…\n\n    private function logStep(`. Se il marcatore manca ⇒ `RuntimeException` |
| `insertDispatchEntry` | `private (string $toolName, string $methodName): void` | Inserisce prima del ramo `default` del `match`. **No-op** se `'<toolName>'` compare già nel file |
| `callLlm` | `private (string $prompt): string` | Forza provider+model |
| `stripFences` | `private (string $text): string` | |
| `extraSection` | `private (string $extra): string` | |

### 7.10 `App\Services\Dashboard`

#### `AIEditorService` — [AIEditorService.php](app/Services/Dashboard/AIEditorService.php)

Singleton. Costanti: `MODEL = 'claude-opus-4-6'`,
`API_URL = 'https://api.anthropic.com/v1/messages'`, `MAX_TOKENS = 8096`.
**Chiama Anthropic direttamente**, senza passare da `LlmRouter`.

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `suggestToolModification` | `suggestToolModification(Tool $tool, string $instruction): string` | Ritorna il nuovo metodo PHP. **Non scrive su disco** |
| `applyToolModification` | `applyToolModification(Tool $tool, string $newMethodCode): void` | `str_replace(vecchio, nuovo)` in `ToolExecutor.php`. `RuntimeException` se il metodo non si trova o se la sostituzione non ha effetto |
| `extractToolMethod` | `extractToolMethod(Tool $tool): string` | Regex `/( {4}private function <nome>\(.*?\): string\n)/` poi conteggio parentesi graffe. `''` se non trova. **Richiede indentazione a 4 spazi e tipo di ritorno `: string`** |
| `suggestSkillModification` | `suggestSkillModification(Skill $skill, string $instruction): string` | Nuovo SKILL.md completo |
| `applySkillModification` | `applySkillModification(Skill $skill, string $newContent): void` | Scrive su `skill_md_path`; `RuntimeException` se vuoto |
| `callClaude` | `private (string $userPrompt): string` | `RuntimeException` se manca `ANTHROPIC_API_KEY` o se l'API risponde male |

#### `EnvEditor` — [EnvEditor.php](app/Services/Dashboard/EnvEditor.php)

Singleton. `__construct(?string $path = null)` → default `base_path('.env')`.

| Metodo | Firma completa | Cosa fa |
|---|---|---|
| `read` | `read(array $keys): array` | `[key => value]` |
| `readOne` | `readOne(string $key): string` | `''` se assente. Toglie le virgolette esterne |
| `write` | `write(array $pairs): void` | Aggiorna in loco o appende. Quota se il valore contiene spazi/`#`/apici/backslash o è vuoto |
| `isWritable` | `isWritable(): bool` | ⚠ mai chiamato |

---

## 8. Controllers, Middleware, Jobs

### 8.1 `TelegramAuthMiddleware` — [TelegramAuthMiddleware.php](app/Http/Middleware/TelegramAuthMiddleware.php)

`handle(Request $request, Closure $next): Response`

1. Logga IP e nomi degli header (`[TelegramAuth] Webhook request received`).
2. Se `flamingdragon.telegram.webhook_secret` **non è vuoto**, confronta con
   `hash_equals()` l'header `X-Telegram-Bot-Api-Secret-Token`. Mismatch ⇒ **HTTP 200 vuoto**.
   ⚠ Se il secret non è configurato **il controllo è saltato del tutto**.
3. Estrae `chat_id`; se null ⇒ HTTP 200 vuoto.
4. `in_array((int)$chatId, allowed_chat_ids, true)`; se no ⇒ HTTP 200 vuoto + warning.

**Perché sempre 200:** Telegram ritenta i webhook che rispondono con errore, e un 403
confermerebbe a un attaccante che l'endpoint esiste. Rispondere 200 muto fa entrambe le cose:
niente retry e nessuna informazione.

### 8.2 `TelegramWebhookController` — [TelegramWebhookController.php](app/Http/Controllers/Api/TelegramWebhookController.php)

**Costruttore:** `TelegramParser $parser`, `TelegramService $telegram`, `CommandRouter $router`,
`AgentSpawner $spawner`, `GeneratorService $generator`, `ConfirmationGate $confirmationGate`.

| Metodo | Firma completa | Note |
|---|---|---|
| `handle` | `handle(Request $request): Response` | Ordine di priorità in §3.1. **Ritorna sempre HTTP 200**, anche su eccezione |
| `handleConfirm` | `private handleConfirm(int $chatId, ?int $messageId): Response` | Nessun pending ⇒ «Nessun comando in attesa di conferma.» Comando sparito ⇒ «Il comando in attesa non è più disponibile.» |
| `handleDeny` | `private handleDeny(int $chatId): Response` | «Comando annullato.» |
| `handlePhoto` | `private handlePhoto(int $chatId, ?int $messageId, array $photo, ?string $caption): void` | Errori → «Sorry, I could not process the image.» |
| `handleVoice` | `private handleVoice(int $chatId, ?int $messageId, array $voice): void` | Errori → «Sorry, I could not process the voice message.» |
| `transcribeWithWhisper` | `private transcribeWithWhisper(string $audioPath): string` | POST diretto a `/v1/audio/transcriptions`, `whisper-1`, `response_format: text`, timeout 120 s |
| `interpretNaturalLanguage` | `private interpretNaturalLanguage(string $text, int $chatId): ?string` | LLM come intent router. Sanifica la risposta con `preg_replace('/[^a-z0-9_]/','')` e la valida contro i comandi esistenti. **Su qualunque errore ritorna `'chat'`** |
| `buildNlCatalog` | `private buildNlCatalog(\Illuminate\Support\Collection $commands): string` | Raggruppa per categoria; aggiunge l'Overview della skill e fino a 4 `display_name` di tool |
| `extractSkillOverview` | `private extractSkillOverview(string $markdown): string` | Prima frase di `## Overview`, troncata a ~120 caratteri |

⚠ `$chatId` in `interpretNaturalLanguage()` non viene usato.

### 8.3 Controller API

| Classe | Metodi (firma) |
|---|---|
| `AgentController` (DI: `SessionManager`) | `index(Request $request): JsonResponse` · `show(string $uuid): JsonResponse` · `cancel(string $uuid): JsonResponse` |
| `ConfigController` | `commandsIndex(): JsonResponse` · `commandsStore(Request): JsonResponse` · `commandsUpdate(Request, int $id): JsonResponse` · `commandsDestroy(int $id): JsonResponse` · `providersIndex(): JsonResponse` · `providersUpdate(Request, int $id): JsonResponse` · `providersSetDefault(int $id): JsonResponse` · `stats(): JsonResponse` · `health(): JsonResponse` · `private checkQueueHealth(): string` |
| `MemoryController` (DI: `MemoryService`) | `index(Request): JsonResponse` · `store(Request): JsonResponse` · `update(Request, int $id): JsonResponse` · `destroy(int $id): JsonResponse` · `backfillEmbeddings(): JsonResponse` · `destroyNamespace(Request): JsonResponse` |
| `SkillController` (DI: `SkillManager`) | `index(): JsonResponse` · `install(Request): JsonResponse` · `toggle(int $id): JsonResponse` · `destroy(int $id): JsonResponse` |
| `ToolController` | `index(): JsonResponse` · `toggle(int $id): JsonResponse` |

### 8.4 Controller dashboard

| Classe | Metodi (firma) | View |
|---|---|---|
| `DashboardController` (DI: `TelegramService`) | `index(): View` | `dashboard.index` |
| `LogViewerController` | `index(Request): View` · `show(string $uuid): View` · `stream(string $uuid)` **(nessun tipo di ritorno)** | `dashboard.logs`, `dashboard.log-detail`, SSE |
| `SettingsController` | `index(): View` · `commands(): View` · `tools(): View` · `memory(): View` · `prompts(): View` · `updateWebhook(Request): JsonResponse` · `saveGlobalPrompt(Request): RedirectResponse` · `saveCommandPrompt(Request, int $id): RedirectResponse` | `dashboard.settings`, `.commands`, `.tools`, `.memory`, `.prompts` |
| `SkillEditorController` (DI: `SkillManager`, `AIEditorService`, `EnvEditor`) | `index(): View` · `show(int $id): View` · `edit(int $id): View` · `update(Request, int $id): RedirectResponse` · `aiSuggest(Request, int $id): JsonResponse` · `aiApply(Request, int $id): JsonResponse` · `saveConfig(Request, int $id): RedirectResponse` · `install(Request): RedirectResponse` | `dashboard.skills`, `.skill-detail`, `.skill-edit` |
| `ToolDetailController` (DI: `AIEditorService`, `EnvEditor`) | `show(int $id): View` · `aiSuggest(Request, int $id): JsonResponse` · `aiApply(Request, int $id): JsonResponse` · `saveConfig(Request, int $id): RedirectResponse` · `saveConfigKeys(Request, int $id): RedirectResponse` | `dashboard.tool-detail` |
| `GeneratorController` (DI: `WebGeneratorService`) | `run(Request): JsonResponse` · `toolsList(): JsonResponse` | — |
| `WizardController` (DI: `TelegramService`) | `index(): View` · `saveEnv(Request): JsonResponse` · `testTelegram(Request): JsonResponse` · `registerWebhook(Request): JsonResponse` · `testLlm(Request): JsonResponse` · `sendTestMessage(Request): JsonResponse` · `private writeEnvValues(array $pairs): void` · `private escapeEnvValue(string $value): string` | `dashboard.wizard` |

**Nota su `WizardController::saveEnv`:** esiste una **whitelist di 8 chiavi** scrivibili —
`FD_TELEGRAM_BOT_TOKEN`, `FD_TELEGRAM_WEBHOOK_SECRET`, `FD_TELEGRAM_ALLOWED_CHAT_IDS`,
`ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `FD_OLLAMA_BASE_URL`, `FD_LLM_DEFAULT_PROVIDER`,
`FD_LLM_DEFAULT_MODEL`. Tutto il resto viene scartato in silenzio. Dopo la scrittura chiama
`Artisan::call('config:clear')`.
`EnvEditor` **non ha whitelist**: le chiavi arrivano da `Tool.config_keys` /
`Skill.env_required`.

### 8.5 `ExecuteAgentJob` — [ExecuteAgentJob.php](app/Jobs/ExecuteAgentJob.php)

`implements ShouldQueue`; usa `Queueable, InteractsWithQueue, SerializesModels`.
`public int $timeout = 600`; `public int $tries = 1` (**nessun retry**).

```php
__construct(private readonly int $sessionId, private readonly string $commandName, private readonly ?int $chatId = null)

public function handle(
    AgentOrchestrator $orchestrator,
    SessionManager    $sessions,
    CommandRouter     $router,
    TelegramService   $telegram,
): void

public function failed(Throwable $exception): void
```

`handle()`: sessione mancante ⇒ log + return. Ri-instrada il comando (se nel frattempo è
uscito dall'allow-list ⇒ `markFailed` + messaggio). Su eccezione: `markFailed` + messaggio
generico «Command 'x' failed. Please check the dashboard for details.»

⚠ `$this->timeout = $session->timeout_seconds` viene assegnato **dentro `handle()`**, cioè
dopo che il worker ha già applicato il timeout: la riassegnazione non ha effetto pratico.

---

## 9. Rotte ed endpoint

### 9.1 API — prefisso `/api`, file [routes/api.php](routes/api.php)

| Metodo | URI | Action | Middleware | Nome |
|---|---|---|---|---|
| POST | `/api/telegram/webhook` | `TelegramWebhookController@handle` | `TelegramAuthMiddleware`, `throttle:60,1` | `telegram.webhook` |

**Blocco `/api/fd/*` — nessuna autenticazione applicativa.**

| Metodo | URI | Action | Input | Output / errori |
|---|---|---|---|---|
| GET | `/api/fd/sessions` | `AgentController@index` | — | paginato 20, con `logs` |
| GET | `/api/fd/sessions/{uuid}` | `AgentController@show` | uuid | 404 se assente |
| POST | `/api/fd/sessions/{uuid}/cancel` | `AgentController@cancel` | uuid | **422** se già terminale |
| GET | `/api/fd/commands` | `ConfigController@commandsIndex` | — | tutti, ordinati |
| POST | `/api/fd/commands` | `ConfigController@commandsStore` | `name`(req,unique,≤100) `description`(req) `category`(req,≤50) `execution_mode`(req, in:sync,async,auto) `timeout_seconds`(1–3600) `tools_allowed`(array) `is_dangerous` `is_active` `skill_required`(≤100) | **201**; 422 su validazione |
| PUT | `/api/fd/commands/{id}` | `ConfigController@commandsUpdate` | tutti opzionali + `skip_confirmation` | 404 / 422 |
| DELETE | `/api/fd/commands/{id}` | `ConfigController@commandsDestroy` | — | `{message}` |
| GET | `/api/fd/skills` | `SkillController@index` | — | |
| POST | `/api/fd/skills/install` | `SkillController@install` | `path`(req,string) | **201**; **422** con `{error}` |
| POST | `/api/fd/skills/{id}/toggle` | `SkillController@toggle` | — | `{is_active}` |
| DELETE | `/api/fd/skills/{id}` | `SkillController@destroy` | — | soft-disattiva |
| GET | `/api/fd/tools` | `ToolController@index` | — | |
| POST | `/api/fd/tools/{id}/toggle` | `ToolController@toggle` | — | `{is_active}` |
| GET | `/api/fd/memory` | `MemoryController@index` | query `search`, `namespace` | paginato 30 |
| POST | `/api/fd/memory` | `MemoryController@store` | `namespace`(req,≤100) `key`(req,≤255) `value`(req) `memory_type`(in: fact,preference,context,instruction) `source`(≤255) `is_important` | **201** |
| PATCH | `/api/fd/memory/{id}` | `MemoryController@update` | `value`(req) `memory_type` `is_important` | Ripassa da `MemoryService::remember()` per rigenerare l'embedding |
| DELETE | `/api/fd/memory/{id}` | `MemoryController@destroy` | — | |
| DELETE | `/api/fd/memory` | `MemoryController@destroyNamespace` | `namespace`(req,≤100) | `{message}` con il conteggio |
| POST | `/api/fd/memory/backfill` | `MemoryController@backfillEmbeddings` | — | `{success, stats:{generated,skipped,failed}}` |
| GET | `/api/fd/providers` | `ConfigController@providersIndex` | — | |
| PUT | `/api/fd/providers/{id}` | `ConfigController@providersUpdate` | `display_name`(≤255) `api_base_url`(url,≤500) `default_model`(≤100) `is_active` `config`(array) | |
| POST | `/api/fd/providers/{id}/set-default` | `ConfigController@providersSetDefault` | — | Azzera gli altri `is_default`. ⚠ **senza effetto pratico** — vedi T12 |
| GET | `/api/fd/stats` | `ConfigController@stats` | — | sessioni per stato + conteggi memory/skill/tool |
| GET | `/api/fd/health` | `ConfigController@health` | — | `{status:'ok', timestamp, queue}` |

Health-check Laravel aggiuntivo: **`GET /up`** (configurato in `bootstrap/app.php`).

### 9.2 Web / dashboard — [routes/web.php](routes/web.php)

**Nessuna autenticazione.** L'accesso va protetto a livello infrastrutturale.

| Metodo | URI | Action | Nome |
|---|---|---|---|
| GET | `/` | `DashboardController@index` | `dashboard` |
| GET | `/logs` | `LogViewerController@index` | `logs.index` |
| GET | `/logs/{uuid}` | `LogViewerController@show` | `logs.show` |
| GET | `/logs/{uuid}/stream` | `LogViewerController@stream` | `logs.stream` |
| GET | `/skills` | `SkillEditorController@index` | `skills.index` |
| GET | `/skills/{id}` | `SkillEditorController@show` | `skills.show` |
| GET | `/skills/{id}/edit` | `SkillEditorController@edit` | `skills.edit` |
| POST | `/skills/{id}/edit` | `SkillEditorController@update` | `skills.update` |
| POST | `/skills/{id}/ai-suggest` | `SkillEditorController@aiSuggest` | `skills.ai-suggest` |
| POST | `/skills/{id}/ai-apply` | `SkillEditorController@aiApply` | `skills.ai-apply` |
| POST | `/skills/{id}/config` | `SkillEditorController@saveConfig` | `skills.save-config` |
| POST | `/skills/install` | `SkillEditorController@install` | `skills.install` |
| GET | `/commands` | `SettingsController@commands` | `commands.index` |
| GET | `/tools` | `SettingsController@tools` | `tools.index` |
| GET | `/tools/{id}` | `ToolDetailController@show` | `tools.show` |
| POST | `/tools/{id}/ai-suggest` | `ToolDetailController@aiSuggest` | `tools.ai-suggest` |
| POST | `/tools/{id}/ai-apply` | `ToolDetailController@aiApply` | `tools.ai-apply` |
| POST | `/tools/{id}/config` | `ToolDetailController@saveConfig` | `tools.save-config` |
| POST | `/tools/{id}/config-keys` | `ToolDetailController@saveConfigKeys` | `tools.save-config-keys` |
| GET | `/memory` | `SettingsController@memory` | `memory.index` |
| GET | `/settings` | `SettingsController@index` | `settings.index` |
| POST | `/settings/update-webhook` | `SettingsController@updateWebhook` | `settings.update-webhook` |
| GET | `/prompts` | `SettingsController@prompts` | `prompts.index` |
| POST | `/prompts/global` | `SettingsController@saveGlobalPrompt` | `prompts.save-global` |
| POST | `/prompts/command/{id}` | `SettingsController@saveCommandPrompt` | `prompts.save-command` |
| POST | `/generator/run` | `GeneratorController@run` | `generator.run` |
| GET | `/generator/tools-list` | `GeneratorController@toolsList` | `generator.tools-list` |
| GET | `/wizard` | `WizardController@index` | `wizard.index` |
| POST | `/wizard/save-env` | `WizardController@saveEnv` | `wizard.save-env` |
| POST | `/wizard/test-telegram` | `WizardController@testTelegram` | `wizard.test-telegram` |
| POST | `/wizard/register-webhook` | `WizardController@registerWebhook` | `wizard.register-webhook` |
| POST | `/wizard/test-llm` | `WizardController@testLlm` | `wizard.test-llm` |
| POST | `/wizard/send-test-message` | `WizardController@sendTestMessage` | `wizard.send-test-message` |

**Sidebar** (definita in `layouts/app.blade.php`, 9 voci): Dashboard, Logs, Skills, Tools,
Commands, Memory, Prompts, Settings, Setup Wizard.

**View Blade (15):** `layouts/app`, `welcome` (default Laravel, non instradata),
`dashboard/{index, logs, log-detail, skills, skill-detail, skill-edit, tools, tool-detail,
commands, memory, prompts, settings, wizard}`.

### 9.3 Codici d'errore

| Situazione | Risposta |
|---|---|
| Webhook: secret errato o chat non autorizzata | HTTP 200 corpo vuoto |
| Webhook: qualsiasi eccezione | HTTP 200 corpo vuoto, errore solo nei log |
| API: risorsa inesistente (`findOrFail`) | 404 |
| API: validazione fallita | 422 con `{errors}` |
| `sessions/{uuid}/cancel` su sessione terminale | 422 `{error}` |
| `skills/install` fallita | 422 `{error}` |
| `ai-suggest`/`ai-apply` senza input | 422 `{error}` |
| `ai-suggest`/`ai-apply` con eccezione | 500 `{error}` |
| `generator/run`: nome < 2 char, descrizione < 10 char, type non valido | 422 `{error}` |
| `generator/run`: eccezione | 500 `{error}` |

---

## 10. Comandi artisan

Tutti in `App\Console\Commands`. Auto-discovery Laravel (nessuna registrazione manuale).

| Signature | Classe | Cosa fa | Exit code |
|---|---|---|---|
| `fd:worker {--queue=default} {--tries=1} {--timeout=600} {--sleep=3}` | `FdWorker` | Wrapper su `queue:work` | quello di `queue:work` |
| `fd:heartbeat` | `FdHeartbeat` | Esegue i task in `heartbeat.tasks`. **Schedulato ogni 30 min**, `withoutOverlapping()`, `runInBackground()` | sempre SUCCESS |
| `fd:backup {--tag=}` | `BackupCommand` | Snapshot in `_backup/<Y-m-d_His>[_tag]/` + `MANIFEST.json` con SHA-256 per file | FAILURE se non crea le dir |
| `fd:restore {backup} {--dry-run} {--force}` | `RestoreCommand` | **Verifica tutti gli hash prima di scrivere qualsiasi cosa**; salta i file identici; tabella di preview; conferma interattiva salvo `--force` | FAILURE se hash KO o copie fallite |
| `fd:export-registry {--target=all}` | `ExportRegistryCommand` | Genera `TOOLS.md` (da DB, tool attivi) e `SKILLS.md` (da `skills/*/SKILL.md` + comandi). Preserva il changelog esistente e registra Added/Removed | FAILURE se `--target` non valido |
| `fd:check-integrity` | `CheckIntegrityCommand` | Ogni `tools_required` in `SKILLS.md` deve esistere come `- name:` in `TOOLS.md` | FAILURE se manca qualche file o c'è un riferimento rotto |
| `fd:sync-embeddings {--file=}` | `SyncEmbeddingsCommand` | Per i 6 file di sistema: legge, genera l'embedding, `updateOrCreate` in `memory` con `namespace='system'`, `key=<filename>`, `memory_type=Context`, `is_important=true`, `source='fd:sync-embeddings'` | FAILURE se manca `OPENAI_API_KEY` o **tutti** i file mancano |

**Metodi interni notevoli**

- `BackupCommand`: `copyDir(string $src, string $dest, string $root): void` ·
  `rel(string $absolute, string $root): string` · `shouldExclude(string $rel, bool $isDir): bool`
  Esclusioni — directory: `vendor`, `node_modules`, `_backup`, `storage/logs`,
  `storage/framework/{cache,sessions,views}`, `bootstrap/cache`, `.git`,
  `storage/app/public/media`. File: `.env` e ogni `.env.*` tranne `.env.example`.
  Estensioni: `jpg jpeg png gif mp3 mp4 wav ogg`. **I PDF non sono esclusi** (di proposito:
  i documenti di discovery sono testo).
- `CheckIntegrityCommand`: `extractNames(string $filePath): array` (regex `/^- name:\s*(\S+)/m`) ·
  `extractSkills(string $filePath): array` (parsing dei blocchi ```` ```yaml ````).
- `ExportRegistryCommand`: costante `TOOL_CATEGORIES` — mappa **tutti e 58** i tool su
  **15 categorie**: `audio, calendario, dati, documenti, email, infrastruttura, rete, sistema,
  skill, social, spesa, telegram, todo, utility, visione`. Oggi **nessun tool finisce in
  `altro`** (il fallback esiste ma non scatta); se aggiungi un tool senza mapparlo qui, ci
  finirà. ·
  `exportTools(): void` · `toolsChangelog(string $destPath, array $newNames, string $timestamp): string` ·
  `exportSkills(): void` · `skillsChangelog(…): string` · `parseFrontmatter(string $content): array`.
- `FdHeartbeat`: `runTask(string $task): void` · `cleanupExpiredSessions(): void`
  (marca `timeout` le sessioni `running` da più di **60 minuti** — valore hardcoded, non
  configurabile — e cancella le dir di sessione più vecchie di `cleanup_after_hours`) ·
  `checkQueueHealth(): void` · `memorySweep(): void` · `removeDirectory(string $dir): void`.

---

## 11. Catalogo dei tool (58)

**Le tre liste che devono restare allineate:**

| Lista | Dove | Conteggio |
|---|---|---|
| Rami del `match` | `ToolExecutor::dispatch()` | **58** |
| Schemi visti dall'LLM | `AgentOrchestrator::buildToolDefinitions()` | **56** ⚠ |
| Righe in `tools` | `DefaultToolsSeeder` | **58** |

I due mancanti negli schemi sono `working_memory_append` e `working_memory_read`: vedi
trappola **T1**.

Legenda: **RL** = `risk_level` seed · **RC** = `risk_category` dopo `RiskCategoryMappingSeeder`
· **Conf** = `requires_confirmation` (⚠ non ha effetto a runtime, vedi T9).

### Sistema / file

| Tool | Metodo | Parametri | RL | RC | Conf | Note |
|---|---|---|---|---|---|---|
| `bash` | `runBash` | `command` (req) | dangerous | bash | ✔ | `exec($cmd.' 2>&1')`. **Nessuna validazione di path, nessuna sanificazione.** Exit code ≠0 ⇒ `"Exit code N:\n…"` |
| `file_read` | `runFileRead` | `path` (req) | moderate | — | — | `validatePath($path)` |
| `file_write` | `runFileWrite` | `path`, `content` (req) | moderate | — | — | `validatePath(dirname($path))` |
| `file_list` | `runFileList` | `path` (def `.`) | safe | — | — | `scandir` meno `.` e `..` |
| `file_delete` | `runFileDelete` | `path` (req) | dangerous | file_delete | ✔ | `unlink` |
| `file_search` | `runFileSearch` | `query` (req), `path` (def base_path), `mode` (`name`\|`content`) | safe | — | — | **max 50 risultati** |
| `process_status` | `runProcessStatus` | — | safe | — | — | `tasklist` su Windows, `ps aux` altrove. **Max 40 righe** |
| `laravel_artisan` | `runArtisan` | `command` (req) | dangerous | bash | ✔ | `PHP_BINARY artisan <cmd>` |

### Infrastruttura

| Tool | Metodo | Parametri | RL | RC | Conf | Note |
|---|---|---|---|---|---|---|
| `git_operation` | `runGit` | `operation` (def `status`), `path` | moderate | git_push | ✔ | **Whitelist: `pull status log diff fetch`** — `push` NON è consentito nonostante la categoria |
| `composer_operation` | `runComposer` | `operation` (def `install`), `path` | moderate | bash | ✔ | Whitelist: `install update dump-autoload` |
| `npm_operation` | `runNpm` | `operation` (def `install`), `script`, `path` | moderate | bash | ✔ | Whitelist: `install run build ci` |

### Rete

| Tool | Metodo | Parametri | RL | Note |
|---|---|---|---|---|
| `http_get` | `runHttpGet` | `url` (req) | moderate | timeout 30 s, **body troncato a 8000 byte** |
| `http_post` | `runHttpPost` | `url` (req), `payload` | moderate | idem |
| `json_api` | `runJsonApi` | `url` (req), `method`, `payload`, `headers` | moderate | GET/POST/PUT/PATCH; risposta in JSON pretty |
| `web_search` | `runWebSearch` | `query` (req), `limit` (max 10) | safe | **DuckDuckGo Instant Answer** — nessuna chiave, ma restituisce solo abstract/related, non risultati web veri |
| `summarize_url` | `runSummarizeUrl` | `url` (req) | safe | `strip_tags` → 6000 caratteri → LLM per il riassunto |
| `weather` | `runWeather` | `location` (req) | safe | Open-Meteo geocoding + forecast, gratuiti. Tabella di 21 `weathercode` |

### Dati / memoria

| Tool | Metodo | Parametri | RL | RC | Note |
|---|---|---|---|---|---|
| `memory_read` | `runMemoryRead` | `key`, `namespace` (def `general`) | safe | — | Senza `key` ritorna tutto il namespace. **Bypassa `MemoryService`** (Eloquent diretto) |
| `memory_write` | `runMemoryWrite` | `key`, `value` (req), `namespace` | safe | — | **Bypassa `MemoryService`: nessun embedding generato** |
| `db_query` | `runDbQuery` | `sql` (req) | moderate | db_destructive | Solo `SELECT` (regex `^SELECT\s`). **Max 50 righe** |
| `cron_list` | `runCronList` | — | safe | — | `Artisan::call('schedule:list')` |
| `working_memory_append` | `runWorkingMemoryAppend` | `line` (req) | safe | — | ⚠ **non dichiarato all'LLM** |
| `working_memory_read` | `runWorkingMemoryRead` | `last_lines` | safe | — | ⚠ **non dichiarato all'LLM** |

### Telegram

| Tool | Metodo | Parametri | RL | RC | Note |
|---|---|---|---|---|---|
| `telegram_send` | `runTelegramSend` | `text` (req) | safe | message_third_party | Manda sempre al **primo** chat ID di `allowed_chat_ids` |
| `send_telegram_image` | `runSendTelegramImage` | `image`\|`url`\|`path` (req), `caption` | safe | — | |
| `send_telegram_voice` | `runSendTelegramVoice` | `audio_path`\|`path` (req), `caption` | safe | — | `.ogg` ⇒ `sendVoice`, altrimenti `sendAudio` |

### Email

| Tool | Metodo | Parametri | RL | RC | Env |
|---|---|---|---|---|---|
| `send_email` | `runSendEmail` | `to`, `subject`, `body` (req) | moderate | message_third_party | `MAIL_*` (6 chiavi) |
| `gmail_list` | `runGmailList` | `query` (def `in:inbox`), `max_results` (max 50), `label` | safe | — | Google (3 chiavi) |
| `gmail_read` | `runGmailRead` | `message_id` (req) | safe | — | idem. **Marca anche come letto**; body troncato a 4000 |
| `gmail_send` | `runGmailSend` | `to`, `subject`, `body` (req), `cc`, `bcc` | moderate | message_third_party | idem. Costruisce RFC 2822 base64url |
| `gmail_search` | `runGmailSearch` | `query` (req), `max_results` | safe | — | idem |
| `gmail_mark_read` | `runGmailMarkRead` | `message_id` (req) | safe | — | idem |
| `gmail_trash` | `runGmailTrash` | `message_id` (req) | dangerous | file_delete | idem |

### Calendario

| Tool | Metodo | Parametri | RL | RC |
|---|---|---|---|---|
| `google_calendar_list` | `runGoogleCalendarList` | `days_ahead` (1–90, def 7), `max_results` (max 50) | safe | — |
| `google_calendar_create` | `runGoogleCalendarCreate` | `title`, `start`, `end` (req, ISO 8601), `description`, `location`, `timezone` (def `Europe/Rome`) | moderate | — |
| `google_calendar_delete` | `runGoogleCalendarDelete` | `event_id` (req) | dangerous | file_delete |

Env: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REFRESH_TOKEN`, `GOOGLE_CALENDAR_ID` (def `primary`).

### Todo (tabella `fd_todos`)

| Tool | Metodo | Parametri | RL |
|---|---|---|---|
| `todo_create` | `runTodoCreate` | `title` (req), `list_name`, `priority` (`low\|normal\|high`), `due_at`, `notes` | safe |
| `todo_list` | `runTodoList` | `list_name` (`all` = tutte), `show_done` | safe |
| `todo_complete` | `runTodoComplete` | `id` (req) | safe |
| `todo_delete` | `runTodoDelete` | `id` (req) | moderate |

⚠ `todo_list` ordina con `orderByRaw("FIELD(priority,'high','normal','low')")` — **`FIELD()` è
MySQL/MariaDB**, non funziona su SQLite o PostgreSQL.

### Spesa (tabella `fd_shopping_items`)

| Tool | Metodo | Parametri | RL |
|---|---|---|---|
| `shopping_add` | `runShoppingAdd` | `name` (req), `list_name`, `category`, `quantity`, `unit` | safe |
| `shopping_items` | `runShoppingItems` | `list_name`, `show_bought` | safe |
| `shopping_bought` | `runShoppingBought` | `id` (req) | safe |
| `shopping_clear` | `runShoppingClear` | `list_name` | moderate |

### Social / messaggistica

| Tool | Metodo | Parametri | RL | RC | Env |
|---|---|---|---|---|---|
| `whatsapp_send` | `runWhatsappSend` | `to` (req), `message` (req), `template` | moderate | message_third_party | `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN` |
| `facebook_post` | `runFacebookPost` | `message` (req), `link`, `image_url` | moderate | message_third_party | `FACEBOOK_PAGE_ID`, `FACEBOOK_PAGE_ACCESS_TOKEN` |
| `facebook_feed` | `runFacebookFeed` | `limit` (max 20) | safe | — | idem |
| `instagram_post` | `runInstagramPost` | `image_url` (req), `caption` | moderate | message_third_party | `INSTAGRAM_BUSINESS_ACCOUNT_ID`, `FACEBOOK_PAGE_ACCESS_TOKEN` |

Tutti su Graph API `v19.0`. `instagram_post` è a due passi: `/media` (container) poi `/media_publish`.

### Documenti

| Tool | Metodo | Parametri | RL | Pacchetto | Output |
|---|---|---|---|---|---|
| `generate_qr` | `runGenerateQr` | `content` (req), `size` (50–1000, def 300) | safe | nessuno (api.qrserver.com) | URL |
| `generate_pdf` | `runGeneratePdf` | `filename`, `html` (req), `title` | safe | `barryvdh/laravel-dompdf` ^3.0 | `storage/generated/<f>.pdf` |
| `generate_docx` | `runGenerateDocx` | `filename`, `title`, `content[]` (`{type,text}`; `type ∈ heading\|table\|paragraph`) | safe | `phpoffice/phpword` ^1.3 | `.docx` |
| `generate_xlsx` | `runGenerateXlsx` | `filename`, `sheet_name`, `headers[]`, `rows[][]` | safe | `phpoffice/phpspreadsheet` ^3.0 | `.xlsx` — **ATTUALMENTE ROTTO, vedi §18 D1** |

Il nome file è sanificato con `preg_replace('/[^a-zA-Z0-9_\-]/','_', …)`.
La directory `storage/app/public/generated` è creata da `ensureGeneratedDir()`.

### Visione / audio

| Tool | Metodo | Parametri | RL | Modello |
|---|---|---|---|---|
| `analyze_image` | `runAnalyzeImage` | `image_path`\|`url` (req), `question` | safe | **`gpt-4o`** — accetta path locale (→ data URI base64) o URL |
| `generate_image` | `runGenerateImage` | `prompt` (req), `size`, `quality` | safe | `dall-e-3` — ritorna l'URL |
| `image_generate` | `runImageGenerate` | `prompt` (req), `size` | safe | `dall-e-3` — ⚠ **duplicato legacy** di `generate_image`, ritorna `"Generated image: <url>"` |
| `transcribe_audio` | `runTranscribeAudio` | `audio_path`\|`path` (req), `language` | safe | `whisper-1`, timeout 120 s |
| `generate_audio` | `runGenerateAudio` | `text` (req), `voice` (def `nova`), `speed` | safe | `tts-1` → MP3 in `storage/app/public/media/tts_<ts>_<md5>.mp3`. Ritorna il **path locale**, non l'URL |

### Skill

| Tool | Metodo | Parametri | RL |
|---|---|---|---|
| `skill_read` | `runSkillRead` | `name` (req) | safe |

---

## 12. Catalogo delle skill (11)

Ogni skill è `skills/<name>/SKILL.md` con frontmatter YAML.

| Skill | Descrizione | `tools_required` | `env_required` | Comando collegato |
|---|---|---|---|---|
| `audio` | Whisper + OpenAI TTS | transcribe_audio, generate_audio, send_telegram_voice | OPENAI_API_KEY | `audio` |
| `contacts` | Rubrica in memoria | memory_read, memory_write | — | ⚠ **nessuno** |
| `document-generator` | QR, PDF, DOCX, XLSX | generate_qr, generate_pdf, generate_docx, generate_xlsx | — | `document` |
| `gmail` | Gmail API v1 | gmail_list, gmail_read, gmail_send, gmail_search, gmail_mark_read, gmail_trash | GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_GMAIL_REFRESH_TOKEN | `gmail` |
| `google-calendar` | Calendar API v3 | google_calendar_list, google_calendar_create, google_calendar_delete | GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REFRESH_TOKEN, GOOGLE_CALENDAR_ID | `calendar` |
| `shopping-list` | Liste della spesa | shopping_add, shopping_items, shopping_bought, shopping_clear | — | `shopping` |
| `site-deploy` | Deploy via git/composer | bash, git_operation, composer_operation, npm_operation, laravel_artisan | — | ⚠ **nessuno** (`deploy_site` ha `skill_required` NULL) |
| `social-media` | Facebook + Instagram | facebook_post, instagram_post, facebook_feed | FACEBOOK_PAGE_ACCESS_TOKEN, FACEBOOK_PAGE_ID, INSTAGRAM_BUSINESS_ACCOUNT_ID | `social` |
| `todo` | Liste di cose da fare | todo_create, todo_list, todo_complete, todo_delete | — | `todo` |
| `vision` | GPT-4o vision + DALL·E 3 | analyze_image, generate_image, send_telegram_image | OPENAI_API_KEY | `vision` |
| `whatsapp` | Meta Cloud API | whatsapp_send | WHATSAPP_PHONE_NUMBER_ID, WHATSAPP_ACCESS_TOKEN | `whatsapp` |

Struttura attesa di un SKILL.md (usata sia da `SkillParser` sia dal generatore):
frontmatter `---` con `name, display_name, version, description, author, tools_required,
env_required`, poi `# Titolo`, `## Overview`, `## Instructions`, `## Examples`, `## Notes`.
⚠ `## Overview` è **semanticamente rilevante**: `extractSkillOverview()` ne estrae la prima
frase per il catalogo del classificatore NL. Rinominare quella sezione degrada il routing NL.

---

## 13. Catalogo dei comandi allow-list (25)

Da `DefaultCommandsSeeder`. Ogni riga può essere modificata dalla dashboard o dall'API.

| Comando | Categoria | Mode | Timeout | Tool concessi | Pericoloso | Skill |
|---|---|---|---|---|---|---|
| `help` | system | sync | 30 | — | no | — |
| `status` | system | sync | 30 | process_status | no | — |
| `deploy_site` | server | async | 900 | bash, git_operation, composer_operation, npm_operation | **sì** | — |
| `server_status` | server | sync | 30 | bash, process_status | no | — |
| `read_file` | dev | sync | 30 | file_read | no | — |
| `write_file` | dev | sync | 60 | file_write | no | — |
| `run_artisan` | dev | sync | 120 | laravel_artisan | **sì** | — |
| `git_pull` | dev | sync | 120 | git_operation | no | — |
| `search_memory` | personal | sync | 30 | memory_read | no | — |
| `create_skill` | dev | async | 600 | file_write, file_read, bash | no | — |
| `morning_briefing` | personal | async | 120 | memory_read, http_get, telegram_send | no | — |
| `run_script` | dev | async | 300 | bash, file_read | **sì** | — |
| `chat` | personal | sync | 60 | memory_read, memory_write, weather, web_search, generate_qr, summarize_url | no | — |
| `generateskill` | dev | sync | 120 | — | no | — |
| `generatetool` | dev | sync | 120 | — | no | — |
| `generateskilltool` | dev | sync | 120 | — | no | — |
| `gmail` | personal | sync | 60 | gmail_* (6) + memory_read | no | gmail |
| `calendar` | personal | sync | 60 | google_calendar_* (3) | no | google-calendar |
| `todo` | personal | sync | 30 | todo_* (4) | no | todo |
| `shopping` | personal | sync | 30 | shopping_* (4) | no | shopping-list |
| `whatsapp` | personal | sync | 30 | whatsapp_send, memory_read | no | whatsapp |
| `social` | personal | sync | 60 | facebook_post, facebook_feed, instagram_post | no | social-media |
| `vision` | personal | sync | 60 | analyze_image, generate_image, send_telegram_image | no | vision |
| `audio` | personal | sync | 60 | transcribe_audio, generate_audio, send_telegram_voice | no | audio |
| `document` | personal | sync | 60 | generate_qr, generate_pdf, generate_docx, generate_xlsx | no | document-generator |

**Comandi pericolosi (gated da `/confirm`): 3** — `deploy_site`, `run_artisan`, `run_script`.

**Meta-comandi intercettati dal controller prima del router** (non sono in `allowed_commands`):
`/confirm`, `/deny`, `/cancel` (solo dentro il generatore).

⚠ `help` è nella allow-list ma **non ha tool concessi e nessun handler dedicato**: viene
eseguito dall'LLM senza strumenti, quindi la lista dei comandi la inventa.

---

## 14. Configurazione e variabili d'ambiente

### 14.1 `config/flamingdragon.php` — ogni chiave

| Chiave | Env | Default | Significato |
|---|---|---|---|
| `telegram.bot_token` | `FD_TELEGRAM_BOT_TOKEN` | — | Token del bot. Letto una volta nel costruttore di `TelegramService` |
| `telegram.webhook_secret` | `FD_TELEGRAM_WEBHOOK_SECRET` | — | **Se vuoto, il controllo del secret è saltato** |
| `telegram.allowed_chat_ids` | `FD_TELEGRAM_ALLOWED_CHAT_IDS` | `[]` | CSV → array di int. **Se vuoto nessuno può usare il bot** |
| `execution.default_timeout` | `FD_DEFAULT_TIMEOUT` | 300 | ⚠ **mai letto dal codice** |
| `execution.max_timeout` | — | 3600 | ⚠ **mai letto dal codice** |
| `execution.sync_threshold` | — | 30 | Soglia per `execution_mode=auto` |
| `execution.max_concurrent_agents` | `FD_MAX_CONCURRENT_AGENTS` | 3 | Limite in `AgentSpawner::spawn()` |
| `execution.session_dir` | — | `storage/app/flamingdragon/sessions` | Base della sandbox |
| `execution.cleanup_after_hours` | — | 24 | Età oltre la quale `fd:heartbeat` cancella le dir |
| `llm.default_provider` | `FD_LLM_DEFAULT_PROVIDER` | `anthropic` | |
| `llm.default_model` | `FD_LLM_DEFAULT_MODEL` | `claude-sonnet-4-20250514` | **Ha la precedenza su `llm_providers.default_model`** |
| `llm.max_tokens` | — | 4096 | Solo Anthropic |
| `llm.temperature` | — | 0.3 | ⚠ **mai passato a nessun provider** |
| `llm.system_prompt_path` | — | `resources/prompts/agent_system.md` | |
| `skills.directory` | — | `base_path('skills')` | |
| `skills.workspace` | — | `storage/app/flamingdragon/skill-workspace` | Usato solo da `SkillGenerator` (non istanziata) |
| `skills.auto_generate` | — | true | ⚠ **mai letto** |
| `skills.require_approval` | — | true | ⚠ **mai letto** |
| `security.allow_list_strict` | — | true | Se false, `isAllowed()` ritorna sempre true |
| `security.dangerous_commands_require_confirmation` | — | true | Interruttore globale del gate |
| `security.max_tool_calls_per_session` | — | 50 | Usato **due volte**: limite del `while` e limite del sandbox |
| `security.blocked_paths` | — | `/etc/shadow`, `/etc/passwd`, `/root/.ssh` | Prefissi bloccati |
| `security.allowed_base_paths` | — | `/home/ubuntu`, `/var/www`, `/tmp/flamingdragon` | ⚠ **path Linux su installazione Windows — vedi T2** |
| `heartbeat.enabled` | — | true | |
| `heartbeat.interval_minutes` | — | 30 | ⚠ **mai letto**: la cadenza è hardcoded in `routes/console.php` |
| `heartbeat.tasks` | — | `cleanup_expired_sessions`, `check_queue_health`, `memory_expiration_sweep` | I soli 3 riconosciuti da `runTask()` |
| `dashboard.enabled` | — | true | ⚠ **mai letto**: la dashboard è sempre raggiungibile |

### 14.2 Tutte le variabili `.env` usate dal codice

| Variabile | Letta da | Obbligatoria per |
|---|---|---|
| `FD_TELEGRAM_BOT_TOKEN` | config | tutto Telegram |
| `FD_TELEGRAM_WEBHOOK_SECRET` | config | autenticazione webhook (consigliata) |
| `FD_TELEGRAM_ALLOWED_CHAT_IDS` | config | **qualunque comando** |
| `FD_LLM_DEFAULT_PROVIDER` / `FD_LLM_DEFAULT_MODEL` | config | scelta modello |
| `FD_MAX_CONCURRENT_AGENTS` / `FD_DEFAULT_TIMEOUT` | config | limiti |
| `FD_OLLAMA_BASE_URL` | `DefaultProvidersSeeder`, wizard | Ollama |
| `ANTHROPIC_API_KEY` | `LlmProvider::resolveApiKey()`, `AIEditorService` | Anthropic + editor AI |
| `OPENAI_API_KEY` | `AppServiceProvider` (EmbeddingService), `ToolExecutor`, webhook | embedding, vision, DALL·E, Whisper, TTS |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | `ToolExecutor::googleOAuthToken()` | Gmail + Calendar |
| `GOOGLE_REFRESH_TOKEN` | `googleAccessToken()` | Calendar (fallback Gmail) |
| `GOOGLE_GMAIL_REFRESH_TOKEN` | `gmailAccessToken()` | Gmail (scope `gmail.modify`) |
| `GOOGLE_CALENDAR_ID` | tool calendario | default `primary` |
| `WHATSAPP_PHONE_NUMBER_ID` / `WHATSAPP_ACCESS_TOKEN` | `runWhatsappSend` | WhatsApp |
| `FACEBOOK_PAGE_ID` / `FACEBOOK_PAGE_ACCESS_TOKEN` | tool social | Facebook + Instagram |
| `INSTAGRAM_BUSINESS_ACCOUNT_ID` | `runInstagramPost` | Instagram |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | Laravel | `send_email` |
| `APP_DEBUG` | `AppServiceProvider::boot()` | **se true disattiva la verifica SSL globale** |
| `DB_CONNECTION`, `DB_DATABASE`, … | Laravel | tutto |

### 14.3 Ambiente verificato (2026-07-31)

| Voce | Valore |
|---|---|
| PHP CLI | **8.3.21** |
| `composer.json` require php | `^8.2` |
| `vendor/composer/platform_check.php` | soglia `80200` — **coerente con composer.json** |
| `DB_CONNECTION` | `mariadb`, database `flamingdragon` |
| Pacchetti | `barryvdh/laravel-dompdf`, `dompdf/dompdf`, `phpoffice/phpword`, `phpoffice/phpspreadsheet` **3.10.3** — tutti installati |

> Il vecchio disallineamento PHP 8.3 CLI / 8.2 Apache è stato risolto abbassando il vincolo
> in `composer.json` a `^8.2`. `platform_check.php` ora è **rigenerato coerentemente** da
> `composer install` e non va più patchato a mano. `FLAMINGDRAGON.md` riporta ancora
> «PHP 8.3 CLI / PHP 8.2 Apache»: è descrittivo, non un problema aperto.

---

## 15. Catalogo dei test

`tests/TestCase.php` estende `Illuminate\Foundation\Testing\TestCase`.
**Nessun test usa `RefreshDatabase`**: nessun test tocca il DB.

### `tests/Unit/Services/Security/ConfirmationGateTest.php` — 9 test

Istanzia `new ConfirmationGate()` direttamente (nessuna dipendenza).
Helper: `makeCommand(string $name='test_cmd', bool $is_dangerous=false, bool $skip_confirmation=false, array $tools_allowed=[]): AllowedCommand` e
`makeParsedCommand(string $name, array $args=[]): ParsedCommand` — costruiscono model
**non persistiti**, quindi i test girano senza DB.

| Test | Cosa dimostra |
|---|---|
| `test_requiresGate_returns_true_for_dangerous_command` | `is_dangerous=true` + `skip_confirmation=false` ⇒ gate attivo |
| `test_requiresGate_returns_false_for_safe_command` | Un comando non pericoloso non è mai gated |
| `test_requiresGate_returns_false_when_skip_confirmation_is_true` | `skip_confirmation` **vince** su `is_dangerous` |
| `test_store_and_retrieve_returns_stored_command` | Il round-trip in cache conserva nome **e** argomenti |
| `test_retrieve_returns_null_for_unknown_chat` | Nessun falso positivo tra chat diverse |
| `test_clear_removes_pending_command` | Dopo `clear()` non c'è più pending (base di `/deny`) |
| `test_clear_on_nonexistent_chat_does_not_throw` | `clear()` è idempotente: `/deny` a vuoto non rompe il webhook |
| `test_buildPrompt_contains_command_name` | L'utente vede **quale** comando sta confermando |
| `test_buildPrompt_contains_confirm_deny_instructions` | Il prompt contiene sempre `/confirm` e `/deny` |

### `tests/Feature/ExampleTest.php` e `tests/Unit/ExampleTest.php`
Stub generati da Laravel. Non dimostrano nulla del dominio. Da rimuovere o sostituire.

**Copertura reale: solo `ConfirmationGate`.** Tutto il resto — orchestrator, tool executor,
sandbox, router, provider LLM, memoria — **non ha test**. Vedi §18 D6.

---

## 16. Regole non negoziabili

1. **Un comando che non è in `allowed_commands` non esiste.** Non aggiungere scorciatoie
   che eseguano codice bypassando `CommandRouter`. `security.allow_list_strict` esiste per il
   debug locale: non spegnerlo su una macchina raggiungibile dalla rete.
2. **Un tool non concesso non si esegue.** Il controllo è
   `in_array($toolName, $grantedTools, true)` dentro il loop dell'orchestrator. Se aggiungi
   un percorso di esecuzione alternativo, replica il controllo.
3. **Mai mandare a Telegram uno stack trace o un messaggio d'errore grezzo.** Tutti i catch
   di alto livello restituiscono stringhe generiche; il dettaglio va nei log.
4. **Il webhook risponde sempre HTTP 200.** Anche su rifiuto e su eccezione. Cambiarlo
   riattiva i retry di Telegram e rivela l'esistenza dell'endpoint.
5. **`TOOLS.md` e `SKILLS.md` non si modificano a mano.** Sono generati da
   `fd:export-registry`. La fonte di verità è il DB (tool) e `skills/*/SKILL.md` (skill).
   Dopo ogni modifica: `php artisan fd:export-registry && php artisan fd:check-integrity`.
6. **`WORKINGMEMORY.md` si scrive solo tramite `WorkingMemoryService::append()`.** La
   scrittura diretta salta il `flock` e la troncatura, e può corrompere il file in caso di
   accessi concorrenti.
7. **Aggiungere un tool richiede tre modifiche coordinate**, altrimenti l'LLM non lo vedrà
   mai o l'esecuzione fallirà:
   a. un ramo nel `match` di `ToolExecutor::dispatch()` + il metodo `run<Nome>()`;
   b. una voce in `AgentOrchestrator::buildToolDefinitions()`;
   c. una riga in `DefaultToolsSeeder` (+ eventualmente `RiskCategoryMappingSeeder` e
      `ExportRegistryCommand::TOOL_CATEGORIES`).
   Infine va aggiunto ai `tools_allowed` di almeno un comando.
8. **Non salvare credenziali nella tabella `memory`.** Quelle stanno in `.env`. La memoria
   finisce nel system prompt e viene inviata al provider LLM.
9. **`APP_DEBUG=false` in produzione.** `AppServiceProvider::boot()` disattiva la verifica
   dei certificati SSL **per tutte le chiamate HTTP** quando il debug è attivo.
10. **La dashboard e `/api/fd/*` non hanno autenticazione.** Devono restare dietro
    VPN / IP allowlist / Cloudflare Access. Non esporli su internet.
11. **`fd:restore` verifica tutti gli SHA-256 prima di scrivere un solo file.** Non
    introdurre un percorso di ripristino che salti la verifica.
12. **Il nome di un tool nel DB deve combaciare esattamente** con la chiave del `match` e,
    per l'editor AI, `Tool::executorMethod()` deve produrre il nome reale del metodo
    (`gmail_send → runGmailSend`).

---

## 17. Trappole già disinnescate

Problemi già incontrati e risolti. **Sono qui perché non ci si faccia mordere due volte.**
Non "semplificare" questo codice senza aver letto la causa tecnica.

### T1 — I tool `working_memory_*` esistono ma l'LLM non li vede
**Sintomo:** l'agente non usa mai la working memory, anche quando avrebbe senso.
**Causa:** `ToolExecutor::dispatch()` ha 58 rami, ma `AgentOrchestrator::buildToolDefinitions()`
ne dichiara **56**: mancano `working_memory_append` e `working_memory_read`. Il modello riceve
solo gli schemi da `buildToolDefinitions()`, quindi non sa che esistano. In più nessun comando
li elenca in `tools_allowed`, quindi anche se li invocasse riceverebbe
*"Tool 'x' is not granted for this session."*
**Stato:** ⚠ **aperto** — vedi §18 D2.

### T2 — `allowed_base_paths` sono path Linux su un'installazione Windows
**Sintomo:** ogni `file_read`/`file_write`/`file_list`/`file_delete`/`file_search` fallisce con
*"Path '…' is outside allowed base paths."*
**Causa:** `security.allowed_base_paths` vale `['/home/ubuntu','/var/www','/tmp/flamingdragon']`.
`SessionSandbox::validatePath()` salta il controllo **solo se l'array è vuoto** — e non lo è.
Su `E:\coding\XAMPP\htdocs\flamingdragon` nessun prefisso combacia, quindi passa solo ciò che
sta dentro la dir di sessione.
**Nota tecnica:** `bash` **non chiama** `validatePath()`, quindi non è soggetto a questo
limite. È incoerente ma è la ragione per cui i comandi `deploy_site`/`run_script` funzionano
mentre `read_file` no.
**Stato:** ⚠ **aperto** — vedi §18 D3.

### T3 — Anthropic rifiuta `[]` dove si aspetta `{}`
**Causa:** PHP deserializza `{}` JSON come array vuoto e `json_encode([])` produce `[]`.
Anthropic richiede un **oggetto** sia in `tools[].input_schema.properties` sia in
`tool_use.input`. Un array vuoto ⇒ 400.
**Rimedio (in due punti, non toccare):**
- `buildToolDefinitions()`: `$properties = empty($def['properties']) ? new \stdClass() : …`
- `normalizeRawBlocks()`: `$block['input'] = empty($input) ? new \stdClass() : $input`

### T4 — Il messaggio assistant va rispedito **identico**
**Causa:** nel multi-turno con tool use, Anthropic pretende che i blocchi `tool_use`
originali siano riproposti nel turno successivo. Ricostruirli dal solo testo non basta.
**Rimedio:** `LlmResponse::$rawBlocks` conserva i blocchi grezzi e l'orchestrator li usa come
`content` del messaggio assistant (normalizzati, vedi T3). `OpenAiProvider` e `OllamaProvider`
**non** popolano `rawBlocks`: lì si ricade sul `content` testuale.

### T5 — Tutti i `tool_result` in **un solo** messaggio user
**Causa:** con più tool call nello stesso turno, Anthropic richiede tutti i `tool_result` in
un unico messaggio `user`. Un messaggio per risultato ⇒ 400.
**Rimedio:** `$toolResultBlocks` accumula, e l'append avviene una volta sola dopo il `foreach`.
`$allFormatted` distingue il formato a blocchi (Anthropic, ci sono i `tool_use_id`) dal
fallback testuale (provider senza ID).

### T6 — L'enum `execution_mode` del DB non ha `auto`
**Causa:** `allowed_commands.execution_mode` accetta `sync|async|auto`, ma
`agent_sessions.execution_mode` accetta **solo `sync|async`**. Scriverci `auto` genera un
errore MySQL.
**Rimedio:** `SessionManager::create()` mappa esplicitamente `Auto → Sync` prima dell'insert.
La risoluzione "vera" (soglia sul timeout) è già avvenuta in `CommandRouter::resolveExecutionMode()`.

### T7 — Il generatore vs. i comandi di generazione
**Causa:** `/generateskill` deve poter **riavviare** il flusso anche a conversazione attiva,
altrimenti l'utente resta intrappolato nella macchina a stati.
**Rimedio:** in `handle()` il ramo "generatore attivo" esclude esplicitamente
`generateskill|generatetool|generateskilltool` prima di inoltrare a `handleMessage()`.

### T8 — La trascrizione vocale mostrata due volte
**Causa:** l'agente `chat` riprende naturalmente ciò che l'utente ha detto; mostrare anche
«🎤 Hai detto: …» rendeva la risposta ridondante.
**Rimedio:** `handleVoice()` mostra la trascrizione **solo se il comando risolto ≠ `chat`**.

### T9 — `Tool.requires_confirmation` non gate niente
**Causa:** il gate legge **esclusivamente** `AllowedCommand.is_dangerous` e `skip_confirmation`
(`ConfirmationGate::requiresGate()`). `Tool.requires_confirmation` **non è letto da nessun
punto del runtime**: compare solo nel seeder e in `TOOLS.md`.
**Conseguenza pratica:** le "promozioni" di `RiskCategoryMappingSeeder`
(`db_query`, `git_operation`, `telegram_send`, `composer_operation`, `npm_operation`
→ `requires_confirmation = true`) **non attivano nessuna conferma**. L'unico effetto reale del
seeder è popolare `risk_category`, che serve all'etichetta in `buildPrompt()`.
Il commento in `RiskCategory.php` («requires_confirmation: gate is active (boolean,
operational)») **descrive un'intenzione, non il comportamento**.
**Stato:** ⚠ **aperto** — vedi §18 D4.

### T10 — `RiskCategoryMappingSeeder` non gira con `db:seed`
**Causa:** `DatabaseSeeder::run()` chiama solo Providers → Tools → Commands.
**Rimedio:** va lanciato a mano:
`php artisan db:seed --class=RiskCategoryMappingSeeder`
**e sempre dopo** `DefaultToolsSeeder`, perché usa `Tool::where(...)->update(...)`: se il tool
non esiste ancora, l'update è un no-op silenzioso.

### T11 — Il config cache stantio durante il wizard
**Causa:** il wizard scrive `.env` e subito dopo prova a mandare un messaggio di test, ma
`config()` nella stessa richiesta PHP ha ancora i valori vecchi.
**Rimedio:** `WizardController::sendTestMessage()` preferisce i valori arrivati **dal form**,
con fallback a catena `request → config → env`. `saveEnv()` chiama `Artisan::call('config:clear')`.

### T12 — Il provider di default **non** viene dal database
**Causa:** `LlmRouter::getProvider()` legge il nome da `config('flamingdragon.llm.default_provider')`
e il modello da `config('flamingdragon.llm.default_model')`. La colonna
`llm_providers.is_default` **non viene mai letta**, e `default_model` è usato solo come
argomento di fallback del `config()` (che ha quasi sempre un valore, quindi non scatta).
**Conseguenza:** l'endpoint `POST /api/fd/providers/{id}/set-default` aggiorna il flag ma
**non cambia niente a runtime**. Per cambiare provider bisogna toccare
`FD_LLM_DEFAULT_PROVIDER` / `FD_LLM_DEFAULT_MODEL` in `.env`.
Coerentemente, `DashboardController::index()` legge da config, non dal DB — così la dashboard
mostra ciò che verrà davvero usato.

### T13 — Codice che riscrive sé stesso
`WebGeneratorService::insertToolMethod()` e `insertDispatchEntry()`, e
`AIEditorService::applyToolModification()`, **modificano `ToolExecutor.php` a runtime**.
Dipendono da marcatori testuali esatti:
- inserimento del metodo: la stringa `    // ====…\n\n    private function logStep(`;
- inserimento nel dispatch: il ramo `default` con la sua indentazione precisa;
- estrazione del metodo: regex che richiede **4 spazi di indentazione** e tipo di ritorno
  **`: string`**.

Riformattare `ToolExecutor.php` (indentazione, ordine dei metodi, tipo di ritorno) **rompe
silenziosamente** l'editor AI e il generatore web. Fai un `fd:backup` prima di usarli.

### T14 — `skill_path` assoluto o relativo a seconda di chi scrive
`SkillManager::install()` scrive un path **assoluto**; `GeneratorService::generateSkill()` e
`WebGeneratorService::generateSkill()` scrivono `"skills/{$name}"` (**relativo**).
`skill_md_path` è invece sempre assoluto, ed è l'unico usato da `readMarkdown()` — per questo
il disallineamento non ha ancora causato bug visibili. Non fidarti di `skill_path`.

---

## 18. Debito tecnico aperto

Ordinati per impatto. Ogni voce dice **perché è stato rimandato** e **quando va affrontata**.

### D1 — `generate_xlsx` è rotto (BUG CONFERMATO)
`runGenerateXlsx()` chiama `$sheet->setCellValueByColumnAndRow(...)`, **rimosso in
PhpSpreadsheet 2.0**. La versione installata è **3.10.3**: il metodo non esiste più in
`vendor/phpoffice/phpspreadsheet/src/`. Ogni invocazione lancia
`Error: Call to undefined method`, che `ToolExecutor::execute()` cattura e restituisce come
*"Tool 'generate_xlsx' failed: …"*.
**Fix:** sostituire con `$sheet->setCellValue([$col+1, $row], $value)` (API a coordinate
dell'attuale versione).
**Perché è rimandato:** il tool non è mai stato esercitato dopo l'aggiornamento del pacchetto.
**Quando:** subito — è l'unica voce di questa sezione che è un guasto certo e non un rischio.

**Bug affine (non fatale):** `runGenerateDocx()` chiama `$phpWord->getDefaultFontName('Calibri')`
e `getDefaultFontSize(11)` — sono **getter** usati come setter. PHP ignora gli argomenti in
eccesso, quindi non si rompe niente, ma **font e corpo non vengono applicati**. Vanno
sostituiti con `setDefaultFontName()` / `setDefaultFontSize()`.

### D2 — I tool `working_memory_*` sono irraggiungibili
Vedi T1. **Fix:** aggiungere le due voci in `buildToolDefinitions()` e inserirle nei
`tools_allowed` di `chat` (e degli altri comandi che ne beneficerebbero).
**Perché rimandato:** introdotti allo step 8/12 come infrastruttura; il cablaggio all'agente
era previsto in una fase successiva mai eseguita.
**Quando:** insieme alla prossima modifica del prompt o del set di tool.

### D3 — La sandbox dei path è inutilizzabile su Windows
Vedi T2. **Fix consigliato:** rendere `allowed_base_paths` dipendente dall'ambiente
(es. `FD_ALLOWED_BASE_PATHS` in `.env`, con `base_path()` come default sensato in locale).
**Perché rimandato:** la config è stata scritta pensando al deploy Linux finale.
**Quando:** prima di usare seriamente i comandi `read_file`/`write_file` in locale, oppure
al primo deploy su Linux (dove invece diventa la protezione corretta e va verificata).

### D4 — Il modello di rischio è a metà
`RiskCategory` + `Tool.requires_confirmation` descrivono un gate **per tool**, ma il gate reale
è **per comando** (`AllowedCommand.is_dangerous`). Vedi T9.
Due strade, da scegliere:
(a) far leggere al gate anche i tool del comando — un comando è pericoloso se lo è almeno uno
dei suoi tool; (b) dichiarare `Tool.requires_confirmation` puramente informativo e correggere
il commento in `RiskCategory.php`.
Va risolto anche il fatto che `riskCategoryLabel()` prende **il primo** tool con categoria non
nulla, quindi con più tool rischiosi l'etichetta mostrata è arbitraria.
**Perché rimandato:** lo step 11/12 ha introdotto la tassonomia, non il cablaggio.
**Quando:** prima di aggiungere altri comandi pericolosi.

### D5 — Codice morto
Mai istanziato o mai chiamato:
`CommandValidator` (intera classe) · `SkillGenerator` (intera classe) ·
`PromptBuilder::buildCapabilityCatalog()` · `AllowListGuard::toolsAreGranted()` ·
`SessionManager::findByUuid()` · `SessionSandbox::cleanup()` ·
`AllowedCommand::resolvedExecutionMode()` · `TelegramParser::isMessage()` ·
`EnvEditor::isWritable()` · `TelegramService::publicMediaUrl()` ·
`AgentOrchestrator::$telegram` e il parametro `$chatId` di `run()` ·
`$chatId` in `interpretNaturalLanguage()` · `resources/views/welcome.blade.php` ·
colonne `agent_sessions.agent_pid`, `queue_job_id`, `tools.handler_class`, `tools.config`,
`tools.input_schema`, `llm_providers.is_default`, `llm_providers.config` ·
config `execution.default_timeout`, `execution.max_timeout`, `llm.temperature`,
`skills.auto_generate`, `skills.require_approval`, `heartbeat.interval_minutes`,
`dashboard.enabled` · `database/database.sqlite` (la connessione è mariadb).
**Quando:** in una pulizia dedicata. Decidere caso per caso se cablare o cancellare —
`CommandValidator` e `toolsAreGranted()` sono difese pensate e mai innestate.

### D6 — Copertura di test quasi nulla
Un solo file di test reale (`ConfirmationGateTest`, 9 test) su ~10.000 righe.
Non sono coperti: il loop dell'orchestrator, `ToolExecutor`, `SessionSandbox::validatePath()`
(che è **codice di sicurezza**), `CommandRouter::resolveExecutionMode()`, i tre provider LLM,
`MemoryService::semanticSearch()`, `WorkingMemoryService::truncate()`.
`ConfirmationGateTest` è il modello giusto: usa model non persistiti, quindi gira senza DB.
**Quando:** priorità a `SessionSandbox::validatePath()` e `resolveExecutionMode()` — logica
pura, facile da testare, alto costo se sbagliata.

### D7 — Tre parser di frontmatter divergenti
`SkillParser::parseContent()` (non estrae `env_required`), `Skill::parseFrontmatter()`,
`ExportRegistryCommand::parseFrontmatter()` (l'unico che gestisce anche le stringhe quotate
con regex dedicata). Tre comportamenti diversi sullo stesso formato.
**Quando:** alla prossima modifica del formato SKILL.md — consolidare su `SkillParser`.

### D8 — Modello Opus hardcoded in 4 punti
`'claude-opus-4-6'` compare in `AgentOrchestrator::run()` (lista `$codeCommands`),
`GeneratorService` (×2), `WebGeneratorService::MODEL`, `AIEditorService::MODEL`.
Nota: la lista `$codeCommands` include `write_file` e `run_script`, che non sono comandi di
generazione codice — forzarli su Opus è probabilmente involontario e costoso.
**Quando:** al prossimo cambio di modello. Spostare in `config/flamingdragon.php`
(es. `llm.code_model`).

### D9 — Indici mancanti
`agent_sessions` non ha indici su `status` né su `created_at`, ma sono le colonne usate da
`countRunning()` (a ogni spawn), dal filtro dei log e da `FdHeartbeat`. Con poche migliaia di
righe non si nota; oltre sì. Anche `execution_logs.session_id` ha solo la FK.
**Quando:** quando `agent_sessions` supera ~50k righe.

### D10 — Ricerca semantica in memoria applicativa
`MemoryService::semanticSearch()` carica **tutte** le memorie con embedding e calcola la
cosine similarity in PHP. Con 1536 dimensioni, il costo cresce linearmente col numero di
memorie e ogni riga porta ~12 KB di JSON.
**Perché rimandato:** MariaDB non ha un tipo vettoriale nativo utilizzabile qui, e la scala
attuale è piccola.
**Quando:** oltre ~1000 memorie con embedding. Alternative: sqlite-vec, pgvector, o un filtro
per namespace prima dello scoring.

### D11 — Vincoli di validazione mancanti
`fd_todos.priority` e `fd_shopping_items` non hanno vincoli DB: la validazione è solo in
`runTodoCreate()`. Nessun'altra strada di scrittura esiste oggi, ma non c'è rete di sicurezza.
`ConfigController::commandsStore()` non valida `tools_allowed` contro i tool esistenti: si può
creare un comando che concede tool inesistenti (falliranno con *"Unknown tool"* a runtime).

### D12 — Note minori
- `PromptBuilder::buildToolContext()` legge `input_schema['required']`, che
  `buildToolDefinitions()` non emette mai: nel prompt **tutti** i parametri risultano
  `*(optional)*`, anche quelli obbligatori.
- `ExecuteAgentJob::handle()` assegna `$this->timeout` dopo che il worker ha già applicato il
  timeout: la riassegnazione non ha effetto.
- `image_generate` e `generate_image` fanno la stessa cosa (DALL·E 3) con output diverso.
  Deprecare `image_generate`.
- `git_operation` ha `risk_category = git_push` ma la whitelist **esclude** `push`.
  Etichetta fuorviante.
- `help` è nella allow-list senza tool né handler: la lista dei comandi la improvvisa l'LLM.
- Le skill `contacts` e `site-deploy` non sono collegate a nessun comando
  (`deploy_site.skill_required` è NULL, benché `site-deploy` esista apposta).
- `FdHeartbeat::cleanupExpiredSessions()` usa 60 minuti hardcoded per la soglia di
  "sessione stantia" — non configurabile.
- `TelegramService` legge il bot token nel costruttore: se `.env` cambia a runtime, l'istanza
  risolta dal container resta con il token vecchio (mitigato solo nel wizard, vedi T11).
- `AllowListGuard::getCommand()` non rispetta `allow_list_strict`, a differenza di
  `isAllowed()`: incoerenza innocua oggi perché il router usa solo `getCommand()`.

---

## 19. Il perché delle scelte non ovvie

Il codice si rilegge; il motivo per cui è così, no.

**Perché una allow-list in DB e non file di configurazione.** I comandi vanno modificati dalla
dashboard e dal generatore AI **senza deploy**. Metterli in un file avrebbe richiesto scrittura
di config a runtime e `config:clear`, con il rischio di lasciare l'app in stato incoerente.

**Perché il webhook risponde sempre 200.** Due motivi che si sommano: Telegram ritenta ogni
webhook che non risponde 2xx (una risposta d'errore genererebbe un loop di retry), e un 403
confermerebbe a chi sonda che l'endpoint esiste ed è protetto. Il 200 muto elimina entrambi.

**Perché Whisper è chiamato dentro il controller e non da un agente.** Spawnare un agente solo
per trascrivere costava una chiamata LLM completa (~20 s) prima ancora di sapere cosa l'utente
avesse chiesto, con rischio concreto di timeout del webhook. La trascrizione è deterministica:
non serve intelligenza, serve una chiamata API.

**Perché il classificatore NL vive nel controller e non in `PromptBuilder`.** `buildNlCatalog()`
è più ricco di `buildCapabilityCatalog()`: raggruppa per categoria, inietta l'Overview della
skill e i `display_name` dei tool. Ha smesso di essere lo stesso oggetto quando il routing è
diventato la parte più delicata dell'esperienza d'uso. `buildCapabilityCatalog()` è rimasto
indietro (§18 D5) — se lo cabli, usa la versione del controller come riferimento.

**Perché `getContext()` fa fallback a cascata.** Se OpenAI non è configurato, se la chiamata
fallisce, o se nessuna memoria ha ancora un embedding, il sistema **deve comunque funzionare**.
La catena semantica → keyword → più recenti garantisce che l'agente riceva sempre un contesto,
degradando la qualità invece della disponibilità.

**Perché l'embedding solo sopra 150 caratteri.** Le memorie brevi (`nome: Ronk`) hanno
embedding poco informativi e la ricerca per chiave le trova comunque. La soglia evita di
spendere chiamate API su contenuti che non ne beneficiano. `is_important` è l'escape hatch per
forzare l'embedding a prescindere.

**Perché `ConfirmationGate` è un servizio a sé.** Prima la logica era sparsa tra
`CommandRouter` (decidere se serve la conferma) e `TelegramWebhookController` (memorizzare il
pending, costruire il messaggio, gestire `/confirm` e `/deny`). Estrarla (step 12/12) ha reso
possibile testarla senza HTTP, senza DB e senza Telegram: `ConfirmationGateTest` istanzia
`new ConfirmationGate()` e usa model non persistiti.

**Perché la cache per il comando pendente e non il DB.** La conferma ha una vita di 5 minuti e
non ha valore storico. La cache dà il TTL gratis; il DB avrebbe richiesto una tabella e un job
di pulizia.

**Perché `fd:backup` usa una blacklist e non una whitelist.** Una whitelist dimentica
silenziosamente i file nuovi — esattamente quando servono, cioè dopo aver aggiunto qualcosa.
La blacklist può copiare qualche file di troppo: un difetto reversibile, a differenza di un
backup incompleto. I PDF sono esclusi dalla lista delle estensioni binarie di proposito: i
documenti di discovery sono a tutti gli effetti testo.

**Perché `fd:restore` verifica tutti gli hash prima di scrivere qualsiasi cosa.** Un ripristino
interrotto a metà lascia il progetto in uno stato che non è né quello vecchio né quello nuovo —
peggio di entrambi. La verifica preventiva rende il fallimento totale e pulito.

**Perché `TOOLS.md`/`SKILLS.md` sono generati e non scritti a mano.** Erano nati a mano ed
erano già divergenti dal DB. Un registro sbagliato è peggio di nessun registro: `fd:check-integrity`
esiste proprio per rendere la divergenza un errore rumoroso invece che un'ipotesi.

**Perché la troncatura della working memory rimuove le righe più vecchie.** È un contesto di
lavoro a breve termine: la riga più recente è quasi sempre la più rilevante. L'header è
preservato perché contiene istruzioni su come leggere il file, non dati.

**Perché `tries = 1` su `ExecuteAgentJob`.** Gli agenti hanno **effetti collaterali reali**:
inviano email, pubblicano su social, cancellano file. Un retry automatico di un job fallito a
metà può eseguire due volte l'azione già riuscita. Meglio fallire una volta sola e farlo sapere.

**Perché il modello è forzato a Opus per i comandi di generazione.** Il codice generato viene
**inserito automaticamente** in `ToolExecutor.php`. Il costo di un modello più capace è
irrilevante rispetto al costo di riparare un file di produzione corrotto.

**Perché `db_query` accetta solo `SELECT`.** L'agente ha bisogno di leggere lo stato del
sistema per rispondere. Dargli DDL/DML avrebbe reso ogni sessione una potenziale perdita di
dati, e il gate di conferma opera a livello di comando, non di singola query.

**Perché `git_operation` esclude `push`.** Le operazioni di lettura (`status`, `log`, `diff`)
e il `pull` sono reversibili o innocue. Un `push` pubblica su un remoto — irreversibile e
visibile ad altri. La whitelist è per costruzione un elenco chiuso: aggiungere un'operazione
richiede una decisione esplicita.

**Perché `SessionSandbox::validatePath()` non applica nulla quando `allowed_base_paths` è
vuoto.** Era la valvola per lo sviluppo su Windows: array vuoto ⇒ nessun limite. La config
attuale però contiene path Linux, quindi il ramo non scatta e il limite si applica sempre
(§17 T2). La valvola c'è, non è azionata.

---

## 20. Cosa NON esiste (ancora)

Cercarlo è tempo perso. Elencato perché l'assenza è informazione.

**Sicurezza e accesso**
- Nessuna autenticazione applicativa: né dashboard, né `/api/fd/*`. Il model `User` esiste ma
  non è usato; nessuna rotta ha `auth`, nessun login, nessun token API.
- Nessun rate limiting oltre `throttle:60,1` sul solo webhook.
- Nessun CSRF sulle rotte POST della dashboard oltre il default Laravel.
- Nessun audit log dei comandi pericolosi eseguiti (esistono `execution_logs`, ma non una
  traccia dedicata "chi ha confermato cosa e quando").
- Nessun interruttore per bypassare globalmente le conferme (esiste solo `skip_confirmation`
  per singolo comando).

**Agente**
- Nessuna conversazione multi-turno: ogni messaggio Telegram apre una sessione nuova, con una
  sola `['role'=>'user']` iniziale. Non c'è finestra di contesto per chat.
- Nessuno streaming delle risposte LLM.
- Nessuna cancellazione di un agente in corso: `POST /sessions/{uuid}/cancel` cambia lo stato
  in DB ma **non interrompe** il processo o il job in esecuzione.
- Nessun `prompt caching` Anthropic, nessun conteggio token reale (`countTokens()` è una stima
  a 4 caratteri/token in tutti e tre i provider).
- Nessun supporto tool per Ollama: `OllamaProvider::chat()` ignora `$tools`.
- Nessun ritentativo automatico delle chiamate LLM fallite.

**Funzionalità**
- Nessuna notifica proattiva: niente briefing mattutino automatico, alert meteo o reminder da
  calendario. Il comando `morning_briefing` esiste ma **nulla lo invoca**: `fd:heartbeat`
  esegue solo pulizia, sweep e health-check.
- Nessun comando `/image` esplicito: le immagini si gestiscono solo mandando una foto.
- Nessuna gestione di documenti/video/sticker in arrivo da Telegram: solo testo, foto e voce.
- Nessun tool di modifica firewall né di scrittura dei file di sistema `.md`, benché
  `RiskCategory::FirewallChange` e `RiskCategory::SystemFileMd` esistano.
- Nessun tool `type` diverso da `builtin`: `script`, `api`, `composite` sono nell'enum ma non
  implementati (`handler_class` non è mai letto).

**Infrastruttura**
- Nessuna migration crea indici su `agent_sessions.status` / `created_at`.
- Nessuna configurazione Docker, CI, o pipeline di deploy.
- Nessun file `.env.example` documentato in questo repository (le chiavi sono in §14.2).
- Nessun frontend buildato: la dashboard usa Tailwind da CDN e Alpine.js, senza Vite.
- `database/database.sqlite` esiste ma **non è la connessione attiva** (è `mariadb`).

---

## Manutenzione di questo documento

**Regola:** se una cosa esiste nel codice e non è qui, il documento è rotto. Se è qui e non
esiste più nel codice, è **peggio**.

**Verifica meccanica delle firme** (eseguire a fine di ogni fase e confrontare con §6–§8):

```bash
grep -rnoE '(public|private|protected)( static)? function [a-zA-Z_]+\([^)]*\)(\s*:\s*[^ {]+)?' \
  app/ --include=*.php | sed 's|app/||'
```

Le firme su più righe (`AgentOrchestrator::run`, `ToolExecutor::execute`,
`SessionManager::markCompleted`, `MemoryService::remember`, `PromptBuilder::build`,
`AgentSpawner::spawn`, `WebGeneratorService::generate`, `ExecuteAgentJob::handle`,
`ToolExecutor::logStep`) **non vengono catturate** da questa regex: vanno controllate a vista.

**Verifica dell'allineamento dei tool** — i tre conteggi devono coincidere (oggi: 58 / 56 / 58,
vedi §18 D2):

```bash
# rami del match in dispatch()
sed -n '/private function dispatch/,/^    }/p' app/Services/Agent/ToolExecutor.php | grep -cE "^\s+'[a-z_]+'\s*=>"
# schemi dichiarati all'LLM
sed -n '/private function buildToolDefinitions/,/^        ];/p' app/Services/Agent/AgentOrchestrator.php | grep -cE "^\s+'[a-z_]+'\s*=>\s*\['description'"
# righe nel seeder
grep -cE "^\s+'name'\s+=> '" database/seeders/DefaultToolsSeeder.php
```

**Verifica dei registri generati:**

```bash
php artisan fd:export-registry && php artisan fd:check-integrity
```
