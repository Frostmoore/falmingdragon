# TODO — FlamingDragon

**Creato:** 2026-07-31
**Origine:** audit completo della codebase → [codebase_reference.md](codebase_reference.md)
**Riferimenti:** ogni voce rimanda alla sezione dell'atlante che la spiega per esteso.

Legenda: 🔴 rotto adesso · 🟠 sicurezza/correttezza · 🟡 costo · ⚪ pulizia

---

## 🔴 P1 — Cose rotte adesso

### 1. `generate_xlsx` non funziona (bug confermato)
- [ ] Sostituire `setCellValueByColumnAndRow()` con `setCellValue([$col, $row], $value)`
  in `runGenerateXlsx()` — [ToolExecutor.php:1380-1421](app/Services/Agent/ToolExecutor.php#L1380-L1421)

**Perché:** il metodo è stato **rimosso in PhpSpreadsheet 2.0**; installata è la **3.10.3**.
Verificato: non esiste più in `vendor/phpoffice/phpspreadsheet/src/`. Ogni chiamata lancia
`Error: Call to undefined method`, che `ToolExecutor::execute()` cattura e restituisce come
*"Tool 'generate_xlsx' failed: …"* — quindi fallisce in silenzio, senza crash visibile.
`getColumnDimensionByColumn()` invece esiste ancora: non va toccato.
→ atlante §18 D1

### 2. `generate_docx`: font e corpo ignorati
- [ ] `getDefaultFontName('Calibri')` → `setDefaultFontName('Calibri')`
- [ ] `getDefaultFontSize(11)` → `setDefaultFontSize(11)`
  — [ToolExecutor.php:1348-1349](app/Services/Agent/ToolExecutor.php#L1348-L1349)

**Perché:** sono **getter usati come setter**. PHP ignora gli argomenti in eccesso, quindi non
si rompe niente — ma le impostazioni di font non vengono mai applicate. Bug silenzioso.
→ atlante §18 D1

### 3. Modello di default deprecato nei fallback
- [ ] Sostituire `claude-sonnet-4-20250514` con `claude-sonnet-5` in:
  - [config/flamingdragon.php:45](config/flamingdragon.php#L45) (default di `llm.default_model`)
  - [AnthropicProvider.php:25](app/Services/Llm/Providers/AnthropicProvider.php#L25) (default del costruttore)
  - [AnthropicProvider.php:97-99](app/Services/Llm/Providers/AnthropicProvider.php#L97-L99) (`getAvailableModels()`)
  - [WizardController.php:32](app/Http/Controllers/Dashboard/WizardController.php#L32)
- [ ] Allineare `DefaultProvidersSeeder` (`default_model` + `available_models`)

**Perché:** `claude-sonnet-4-20250514` è **deprecato**, con data di ritiro pubblicata al
**2026-06-15** — già passata. Oggi non impatta perché `.env` imposta
`FD_LLM_DEFAULT_MODEL=claude-haiku-4-5-20251001`, ma **il giorno che quella riga sparisce dal
`.env` il fallback punta a un modello che può rispondere 404**. È una mina innescata.

Mappa modelli attuale:

| Ora | Sostituto | Prezzo in/out per MTok |
|---|---|---|
| `claude-sonnet-4-20250514` (deprecato) | `claude-sonnet-5` | $3 / $15 ($2/$10 introduttivo fino al 2026-08-31) |
| `claude-opus-4-6` (vecchio) | `claude-opus-5` | $5 / $25 |
| `claude-haiku-4-5-20251001` ✅ | va bene | $1 / $5 |

---

## 🟠 P2 — Sicurezza e correttezza

### 4. `Tool.requires_confirmation` non attiva nessuna conferma
- [ ] Decidere fra:
  - **(a)** far leggere al gate anche i tool del comando — un comando è pericoloso se lo è
    almeno uno dei suoi `tools_allowed`; oppure
  - **(b)** dichiarare il campo puramente informativo e **correggere il commento** in
    [RiskCategory.php:9-10](app/Enums/RiskCategory.php#L9-L10) che oggi dice il falso
- [ ] Sistemare `riskCategoryLabel()`: prende **il primo** tool con categoria non nulla, quindi
  con più tool rischiosi l'etichetta mostrata all'utente è arbitraria
  — [ConfirmationGate.php:97-118](app/Services/Security/ConfirmationGate.php#L97-L118)

**Perché:** `ConfirmationGate::requiresGate()` legge **solo** `AllowedCommand.is_dangerous` e
`skip_confirmation`. Il grep conferma che `Tool.requires_confirmation` **non è mai letto a
runtime**: compare solo nel seeder e in `TOOLS.md`. Conseguenza concreta: le "promozioni" di
`RiskCategoryMappingSeeder` (`db_query`, `git_operation`, `telegram_send`,
`composer_operation`, `npm_operation` → `requires_confirmation = true`) **non chiedono nessuna
conferma**. Oggi i comandi realmente gated sono solo 3: `deploy_site`, `run_artisan`, `run_script`.
→ atlante §17 T9, §18 D4

### 5. La sandbox dei path è inutilizzabile su Windows
- [ ] Rendere `security.allowed_base_paths` dipendente dall'ambiente
  (es. `FD_ALLOWED_BASE_PATHS` in `.env`, con `base_path()` come default in locale)
  — [config/flamingdragon.php:77-81](config/flamingdragon.php#L77-L81)

**Perché:** i path sono Linux (`/home/ubuntu`, `/var/www`, `/tmp/flamingdragon`) ma il progetto
gira su `E:\coding\XAMPP\htdocs\flamingdragon`. `validatePath()` salta il controllo **solo se
l'array è vuoto** — non lo è. Risultato: `file_read`, `file_write`, `file_list`, `file_delete`,
`file_search` falliscono sempre tranne dentro la dir di sessione.
Nota: `bash` **non** passa da `validatePath()`, quindi non è soggetto al limite — per questo
`deploy_site`/`run_script` funzionano mentre `read_file` no.
→ atlante §17 T2, §18 D3

### 6. `RiskCategoryMappingSeeder` non gira con `db:seed`
- [ ] Aggiungerlo a `DatabaseSeeder::run()` **dopo** `DefaultToolsSeeder`
  — [DatabaseSeeder.php:13-17](database/seeders/DatabaseSeeder.php#L13-L17)

**Perché:** oggi va lanciato a mano (`php artisan db:seed --class=RiskCategoryMappingSeeder`),
e usa `Tool::where(...)->update(...)`: se i tool non esistono ancora, l'update è un **no-op
silenzioso**. Su un DB ricreato da zero, `risk_category` resta NULL ovunque.
→ atlante §17 T10

### 7. Nessuna autenticazione su dashboard e API
- [ ] Verificare che `/` e `/api/fd/*` **non** siano raggiungibili da internet
- [ ] Se lo sono: mettere davanti VPN / IP allowlist / Cloudflare Access, oppure aggiungere
  un middleware di auth

**Perché:** nessuna rotta ha `auth`, nessun login, nessun token. Chi raggiunge la dashboard può
eseguire l'editor AI, che **riscrive `ToolExecutor.php`**.
→ atlante §16 regola 10, §20

---

## 🟡 P3 — Costo LLM

> Contesto: l'agente parla con l'**API Anthropic a consumo**. L'abbonamento Claude non copre
> queste chiamate (vedi §Note in fondo). Quindi l'unica leva reale è **spendere meno token**.

### 8. Aggiungere il prompt caching (la leva più grossa)
- [ ] Marcare il system prompt con `cache_control` in `AnthropicProvider::chat()`
  — [AnthropicProvider.php:42-44](app/Services/Llm/Providers/AnthropicProvider.php#L42-L44)
- [ ] Verificare i risultati leggendo `usage.cache_read_input_tokens` nella risposta
- [ ] Loggarlo in `execution_logs` per misurare il risparmio reale

**Perché:** **oggi non c'è alcun prompt caching** (`grep cache_control app/` → zero risultati).
Nel loop agentico ogni iterazione rispedisce system prompt + skill + memorie + schemi tool +
tutta la conversazione, sempre a prezzo pieno. Le letture da cache costano **~0,1×**
l'input normale; la scrittura costa ~1,25×, quindi si va in pari **dalla seconda chiamata** —
e una sessione agentica ne fa 5–50.

Regole da rispettare (il caching è un **match di prefisso**: un byte diverso invalida tutto
quello che segue):
- l'ordine di rendering è `tools` → `system` → `messages`;
- il breakpoint va sull'**ultimo blocco system**, così copre tools + system insieme;
- il prefisso minimo cacheabile dipende dal modello: **512 token** su Opus 5, **1024** su
  Sonnet 5, **4096** su Haiku 4.5 ⚠️ — con Haiku (il default attuale) prompt corti **non
  verranno cachati affatto**, in silenzio;
- **non mettere niente di volatile prima del breakpoint** — oggi `PromptBuilder::build()`
  inietta le memorie (che cambiano) *prima* del blocco tool: va riordinato mettendo per primo
  ciò che è stabile.

### 9. Il classificatore NL fa una chiamata LLM per ogni messaggio
- [ ] Forzare `claude-haiku-4-5` in `interpretNaturalLanguage()`
  — [TelegramWebhookController.php:346-350](app/Http/Controllers/Api/TelegramWebhookController.php#L346-L350)
- [ ] Valutare una cache del catalogo (cambia solo quando cambiano i comandi)
- [ ] Valutare uno short-circuit: se il testo combacia con un nome comando, saltare l'LLM

**Perché:** ogni messaggio in linguaggio naturale (cioè quasi tutti) fa **una chiamata in più**
solo per scegliere il nome del comando, spedendo l'intero catalogo. Usa il modello di default.
Il compito è banale: Haiku basta e costa 1/3 di Sonnet 5, 1/5 di Opus 5.

### 10. Opus hardcoded in 6 punti, due dei quali probabilmente involontari
- [ ] Spostare il modello in configurazione (es. `llm.code_model`)
- [ ] **Rimuovere `write_file` e `run_script`** dalla lista `$codeCommands`
  — [AgentOrchestrator.php:55](app/Services/Agent/AgentOrchestrator.php#L55)
- [ ] Aggiornare `claude-opus-4-6` → `claude-opus-5` in tutti i punti rimasti

**Perché:** `$codeCommands` forza Opus su `generateskill`, `generatetool`, `generateskilltool`,
`create_skill`, **`write_file`** e **`run_script`**. Gli ultimi due non generano codice:
scrivere un file o eseguire uno script a $5/$25 per MTok invece che a $1/$5 è spreco puro.
Punti hardcoded: `AgentOrchestrator:58`, `ToolExecutor:1428`, `AIEditorService:18`,
`GeneratorService:252` e `:302`, `WebGeneratorService:22`.
→ atlante §18 D8

### 11. Impostare `effort` esplicitamente
- [ ] Aggiungere `output_config: {effort: ...}` in `AnthropicProvider::chat()`, configurabile
  per comando

**Perché:** `effort` è oggi il principale controllo costo/qualità e **non viene mai impostato**
(il default è `high`). Per i comandi semplici (`todo`, `shopping`, `status`, il classificatore)
`low` o `medium` basta ampiamente. Nota: `flamingdragon.llm.temperature` esiste in config ma
**non è mai passato a nessun provider** — ed è comunque un parametro rimosso sui modelli
recenti, quindi va cancellato, non cablato.

---

## ⚪ P4 — Debito tecnico

### 12. Due tool irraggiungibili
- [ ] Aggiungere `working_memory_append` e `working_memory_read` a `buildToolDefinitions()`
  — [AgentOrchestrator.php:257-345](app/Services/Agent/AgentOrchestrator.php#L257-L345)
- [ ] Inserirli nei `tools_allowed` di `chat` (e di chi altro ne beneficia)

**Perché:** sono nel `match` di `ToolExecutor` (58 rami) e nel seeder, ma **non fra i 56 schemi
dichiarati all'LLM**: il modello non sa che esistano. Verifica: `58 / 56 / 58`.
→ atlante §17 T1, §18 D2

### 13. Copertura di test quasi nulla
- [ ] Test per `SessionSandbox::validatePath()` — **è codice di sicurezza**
- [ ] Test per `CommandRouter::resolveExecutionMode()` — logica pura, facile
- [ ] Rimuovere i due `ExampleTest.php` stub

**Perché:** un solo file di test reale (`ConfirmationGateTest`, 9 test) su ~10.000 righe.
`ConfirmationGateTest` è il modello giusto: usa model non persistiti, quindi gira senza DB.
→ atlante §15, §18 D6

### 14. Codice morto — decidere se cablare o cancellare
- [ ] `CommandValidator` (mai istanziata) — era una difesa pensata e mai innestata
- [ ] `AllowListGuard::toolsAreGranted()` (mai chiamata) — idem
- [ ] `SkillGenerator` (mai istanziata)
- [ ] `PromptBuilder::buildCapabilityCatalog()` — superata da `buildNlCatalog()` nel controller
- [ ] Colonne mai lette: `agent_sessions.agent_pid`, `queue_job_id`, `tools.handler_class`,
  `tools.config`, `tools.input_schema`, `llm_providers.is_default`, `llm_providers.config`
- [ ] Config mai lette: `execution.default_timeout`, `execution.max_timeout`, `llm.temperature`,
  `skills.auto_generate`, `skills.require_approval`, `heartbeat.interval_minutes`,
  `dashboard.enabled`
- [ ] `database/database.sqlite` (la connessione attiva è mariadb)

→ atlante §18 D5 per l'elenco completo

### 15. Tre parser di frontmatter divergenti
- [ ] Consolidare su `SkillParser` (che oggi è l'unico a **non** estrarre `env_required`)

→ atlante §18 D7

### 16. Note minori
- [ ] `POST /api/fd/providers/{id}/set-default` **non fa niente**: il provider viene da config,
  non dal DB → o cablarlo o rimuovere l'endpoint (atlante §17 T12)
- [ ] `PromptBuilder::buildToolContext()` legge `input_schema['required']` che
  `buildToolDefinitions()` non emette mai → nel prompt **tutti** i parametri risultano
  `*(optional)*`, anche quelli obbligatori
- [ ] `image_generate` duplica `generate_image` → deprecare
- [ ] `git_operation` ha `risk_category = git_push` ma la whitelist **esclude** `push` →
  etichetta fuorviante
- [ ] `help` è nella allow-list senza tool né handler → la lista dei comandi la inventa l'LLM
- [ ] Skill `contacts` e `site-deploy` non collegate a nessun comando
  (`deploy_site.skill_required` è NULL benché `site-deploy` esista apposta)
- [ ] `ExecuteAgentJob::handle()` assegna `$this->timeout` **dopo** che il worker l'ha già
  applicato → riassegnazione senza effetto
- [ ] Indici mancanti su `agent_sessions.status` e `created_at` (servono oltre ~50k righe)
- [ ] `FdHeartbeat` usa 60 minuti hardcoded per la soglia "sessione stantia"

---

## Note

### Sull'abbonamento Claude vs API

**FlamingDragon usa l'API a consumo e non può usare i limiti dell'abbonamento Claude.**

`AnthropicProvider` chiama `https://api.anthropic.com/v1/messages` con header `x-api-key:
$ANTHROPIC_API_KEY`. Quella è la **Claude Developer Platform**, fatturata a token sull'account
API. L'abbonamento Claude (Pro/Max) copre le **applicazioni Claude** — claude.ai, Claude Code,
desktop e mobile — ed è un contratto separato: non esiste un parametro, un header o un
endpoint che faccia pescare una chiamata API dai limiti dell'abbonamento.

**`ant auth login` non risolve il problema.** Sostituisce la chiave statica con un token OAuth
a vita breve, ma il token resta **legato a un'organizzazione + workspace API**: stessa
fatturazione, solo credenziali più comode.

Quindi le strade reali sono tre:

1. **Ridurre la spesa API** — è ciò che coprono i punti 8–11 qui sopra. Il prompt caching da
   solo può togliere circa il 90% del costo di input sulla parte cachata, e oggi non c'è.
2. **Ollama in locale** — gratis e **già cablato** (`OllamaProvider`, provider nel seeder).
   Limite grosso: `OllamaProvider::chat()` **ignora `$tools`** e ritorna sempre
   `toolCalls: []`, quindi non regge il loop agentico. Utilizzabile subito solo per il comando
   `chat` e per il classificatore NL — che è comunque una quota non piccola del traffico.
3. **Claude Code in headless (`claude -p`)** — usa le credenziali di Claude Code, che per un
   utente Pro/Max sono quelle dell'abbonamento. Da valutare con attenzione:
   - **non può sostituire il loop agentico**: `-p` esegue i *suoi* tool, non accetta gli schemi
     tool di FlamingDragon e non restituisce blocchi `tool_use`. Servirebbe riesporre i 58 tool
     come server MCP — riscrittura sostanziale;
   - i limiti dell'abbonamento sono a finestre (non a token) e un demone sempre acceso li
     consuma in modo poco prevedibile;
   - l'abbonamento è pensato per uso interattivo personale, non per alimentare un servizio
     server-side: prima di andarci sopra a regime conviene verificare che l'uso rientri nei
     termini.

   Se ti interessa, la porzione sensata è usarlo **solo per le chiamate senza tool**
   (classificatore NL, `summarize_url`, i generatori di skill/tool): sono quelle a volume alto
   e struttura semplice, quindi il grosso del risparmio con il minimo di riscrittura.

**Suggerimento:** fai prima i punti 8, 9 e 10 e misura. Con caching + Haiku sul classificatore
+ Opus tolto da `write_file`/`run_script` il conto scende molto, senza toccare l'architettura.

### Rituale dopo ogni modifica ai tool o alle skill

```bash
php artisan fd:export-registry && php artisan fd:check-integrity
```

`TOOLS.md` e `SKILLS.md` sono **generati**: non si modificano a mano.

### Prima di usare l'editor AI o il generatore web

```bash
php artisan fd:backup --tag=pre-ai-edit
```

Entrambi **riscrivono `ToolExecutor.php` a runtime** e dipendono da marcatori testuali esatti
(indentazione a 4 spazi, tipo di ritorno `: string`, il ramo `default` del `match`).
Riformattare quel file li rompe in silenzio. → atlante §17 T13
