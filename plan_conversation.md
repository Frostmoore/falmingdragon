# plan_conversation.md — Conversazione, contesto e gate per azione

**Versione piano:** 1.0.0
**Data:** 2026-07-31
**Stato generale:** ⬜ Non iniziato
**Sostituisce:** il documento di design «FlamingDragon — Conversazione, contesto e gate per
azione» v1.0.0, di cui conserva tutte le decisioni e ne scioglie i nodi aperti.
**Documenti collegati:** [codebase_reference.md](codebase_reference.md) (atlante — riferimenti
a §, T e D puntano lì) · [todo.md](todo.md) (debito corrente) ·
[plan_deploy.md](plan_deploy.md) (deploy, indipendente)

---

> **Principio guida.** Niente euristica dove può esserci struttura. Le soglie decidono
> *quando* fare le cose, mai *cosa* significano. Ogni componente non deterministico deve avere
> come modalità di fallimento peggiore la **qualità degradata**, mai la **corruzione dello
> stato**.
>
> **Corollario operativo.** Ogni fase deve poter essere abbandonata a metà lasciando il sistema
> funzionante. Se una fase non è reversibile, va spezzata finché non lo è.

---

## Indice

| § | Sezione |
|---|---|
| 0 | [Come si usa questo piano](#0-come-si-usa-questo-piano) |
| 1 | [Diagnosi](#1-diagnosi) |
| 2 | [Nodi sciolti](#2-nodi-sciolti-decisioni-che-il-documento-di-design-lasciava-aperte) |
| 3 | [Architettura target](#3-architettura-target) |
| 4 | [Ordine delle fasi e perché](#4-ordine-delle-fasi-e-perché) |
| 5 | [Fase 0 — Baseline](#fase-0--baseline-e-rete-di-sicurezza) |
| 6 | [Fase 1 — Event sourcing, proiezione, validatore](#fase-1--event-sourcing-proiezione-validatore) |
| 7 | [Fase 2 — Conversazione + gate per azione](#fase-2--conversazione--gate-per-azione) |
| 8 | [Fase 3 — Compattazione](#fase-3--compattazione-del-contesto) |
| 9 | [Fase 4 — Registry dei tool](#fase-4--registry-dei-tool-e-generatore-sicuro) |
| 10 | [Rischi e rollback](#10-rischi-e-rollback) |
| 11 | [Cosa NON facciamo](#11-cosa-non-facciamo-in-questo-piano) |
| 12 | [Obiezioni registrate](#12-obiezioni-registrate) |

---

## 0. Come si usa questo piano

### 0.1 Rituale di fine fase — obbligatorio

Alla fine di **ogni fase**, senza aspettare che venga chiesto, nell'ordine:

1. **Aggiornare questo file** — spuntare le checkbox della fase e delle sottofasi, aggiornare
   lo «Stato generale» in testa, annotare eventuali scoperte che cambiano le fasi successive.
2. **Aggiornare `codebase_reference.md`** — ogni fase elenca sotto la voce «Rituale» esattamente
   quali sezioni toccare. Poi eseguire la **verifica meccanica delle firme** documentata in
   fondo all'atlante e confrontarla con §6–§8.
3. **Messaggio di fine fase estremamente dettagliato**, contenente:
   - stato dell'implementazione;
   - checkbox su fase e sottofasi;
   - commento sullo stato generale **e** su quello della fase specifica;
   - cosa è emerso che non era previsto.
4. **Commit e push su un branch di versione nuovo** (§0.2).

### 0.2 Versionamento dei branch

| Fase | Entità | Branch |
|---|---|---|
| Fase 0 | Piccola | `v1.0.1` |
| Fase 1 | Grande | `v2.0.0` |
| Fase 2 | Grande | `v3.0.0` |
| Fase 3 | Media | `v3.1.0` |
| Fase 4 | Grande | `v4.0.0` |

I commit intermedi **dentro** una fase incrementano la patch (`v2.0.0` → `v2.0.1` → …). Il
branch prende il nome della versione con cui la fase si **apre**.

### 0.3 Verifiche da eseguire a ogni commit

```bash
php artisan test                                    # deve passare, sempre
php artisan fd:export-registry && php artisan fd:check-integrity
php -l <ogni file toccato>
```

---

## 1. Diagnosi

Il sistema attuale è **un dispatcher di comandi one-shot con la faccia di un assistente**.
Verificabile nel codice: `SessionManager::create()` apre una `agent_session` nuova per ogni
messaggio, e `AgentOrchestrator::run()` costruisce `$messages` con **una sola**
`['role' => 'user', 'content' => $session->raw_input]`. Non esiste finestra di contesto.

Il caso che rompe tutto è banale:

> «Manda una mail a Marco» → «no aspetta, cambia l'oggetto»

Il secondo messaggio passa da `interpretNaturalLanguage()` **senza contesto**, finisce sul
comando `chat`, che ha `tools_allowed = [memory_read, memory_write, weather, web_search,
generate_qr, summarize_url]` — nessun tool Gmail. L'agente risponde a vuoto.

**Il punto strutturale.** Tutta l'architettura del routing per comando (classifica → scegli
comando → concedi i suoi tool → spawna) è figlia del design one-shot. Se ogni messaggio deve
essere autonomo, ha senso indovinare l'intento ogni volta. **Se esiste una conversazione,
indovinare l'intento a ogni messaggio è il baco**, non una feature da rattoppare.

Sono state considerate e scartate le mitigazioni euristiche — comando "appiccicoso", soglie di
incertezza del classificatore, timeout di inattività che decidono semantica. Quando sbagliano
non producono un errore: producono un comportamento silenziosamente sbagliato.

**Secondo problema, intrecciato.** Il codice che riscrive sé stesso
(`WebGeneratorService::insertToolMethod()`, `AIEditorService::applyToolModification()` — T13):
chirurgia a marcatori testuali su un file di 1728 righe. Una riformattazione e si rompe in
silenzio.

---

## 2. Nodi sciolti (decisioni che il documento di design lasciava aperte)

Ognuna di queste ha una motivazione e un'alternativa scartata. Sono decisioni, non opzioni.

### N1 — Come si concilia «l'agente sceglie i tool» con «non dargliene 58»

**Il problema.** Il caso motivante («cambia l'oggetto») richiede che i tool Gmail siano
disponibili nel profilo generale. Ma se il profilo generale contiene Gmail, calendario,
WhatsApp, social, documenti… siamo di nuovo a 58 tool, che è il costo che i profili dovevano
evitare. Il documento di design riconosceva il costo ma non scioglieva la contraddizione.

**Decisione.** Il profilo resta **stato dichiarato della conversazione**, e l'agente può
cambiarlo **chiamando un tool**:

```
switch_profile(profile: string)   // enum dei profili attivi
```

- Il profilo generale contiene un set **piccolo e curato** (memoria, meteo, ricerca, chat) più
  `switch_profile`.
- L'agente che riceve «manda una mail a Marco» chiama `switch_profile('gmail')`, ottiene i tool
  Gmail dal turno successivo, e procede.
- «Cambia l'oggetto» arriva quando il profilo è **già** gmail: nessun cambio, nessuna
  classificazione, i tool sono lì.
- Il cambio finisce nel log come evento `profile_changed`: **esplicito, ispezionabile,
  reversibile**, visibile in dashboard. Non è inferenza per messaggio, è un'azione registrata.

**Perché non l'alternativa.** Il *deferred tool loading* (dichiarare tutti i tool con
`defer_loading` e lasciare che il modello li scopra) è più elegante e non invalida la cache,
ma è legato a modelli e beta specifici e toglie l'ispezionabilità dello stato. `switch_profile`
funziona su qualunque modello e lascia una traccia. Il deferred loading resta come evoluzione
futura (§11).

**Costo accettato.** Un cambio di profilo cambia il set di tool, che sta in **testa** al
prefisso: invalida tutta la cache del prompt. Accettabile perché raro (una o due volte per
conversazione). Va **misurato**: se i cambi risultano frequenti, il set del profilo generale è
sbagliato.

### N2 — Serializzazione: append-only non basta

**Il problema.** «Sequence monotonico per conversazione» e «non può corrompersi» non seguono
l'uno dall'altro. Oggi tre messaggi ravvicinati aprono tre sessioni indipendenti che non si
pestano i piedi. Con una conversazione sola diventano **tre scrittori sullo stesso log**, e la
proiezione di eventi interlacciati è spazzatura.

**Decisione — due meccanismi distinti.**

1. **Assegnazione del sequence: atomica a livello DB.**
   `UNIQUE(conversation_id, sequence)` + assegnazione dentro una transazione con
   `SELECT ... FOR UPDATE` su `conversations.last_sequence`. Un conflitto è un errore, non una
   corruzione silenziosa.
2. **Esecuzione del loop: un solo loop per conversazione.**
   `conversations.status` ∈ `idle | running | awaiting_confirmation | closed`.
   Solo una transizione `idle → running` può riuscire (update condizionale
   `WHERE status = 'idle'`, controllo delle righe affette).

**Cosa succede a un messaggio che arriva a loop in corso.** Viene **sempre** appeso al log
(l'append è sicuro). Poi:

- se la conversazione è `running`, non si dispatcha niente: il loop, che **riproietta a ogni
  iterazione**, lo assorbe naturalmente al giro successivo;
- se è `idle`, si dispatcha un job.

**La corsa da chiudere.** Un messaggio che arriva *dopo* l'ultima proiezione del loop ma
*prima* che il loop rilasci il lock resterebbe non processato. Rimedio: alla fine del loop,
**dentro il lock**, si controlla se esistono `user_message` con sequence maggiore dell'ultimo
proiettato; se sì il loop continua invece di uscire.

### N3 — Il prompt caching è un vincolo architetturale della proiezione

**Il problema.** Il documento di design non nominava il caching. Ma la Decisione B moltiplica
la dimensione del prefisso rispedito a ogni turno: **senza caching questo disegno costa più di
quello attuale, non meno**. Oggi il caching non esiste affatto (`grep cache_control app/` → 0).

**Decisione.** La proiezione deve produrre un prefisso **byte-identico** turno su turno per una
data conversazione+profilo. Questo impone tre regole, che sono **architettura, non ottimizzazione**:

1. **Ordine di rendering.** L'API rende `tools` → `system` → `messages`. Il breakpoint di cache
   va sull'**ultimo blocco system**, così copre tool + system insieme.
2. **Niente di volatile nel prefisso.** Oggi `PromptBuilder::build()` inietta le memorie da
   `MemoryService::getContext($session->raw_input)` — ricerca semantica **sull'input corrente**,
   quindi diverse a ogni messaggio — e le mette *prima* del blocco tool. Con quell'ordine la
   cache non si aggancia mai.
3. **Le memorie diventano due cose distinte** (§N4).

**Come si verifica che funzioni.** `usage.cache_read_input_tokens` > 0 dal secondo turno in poi.
Se è zero, c'è un invalidatore silenzioso: si fa il diff byte a byte di due prefissi consecutivi.
Va loggato per evento (§Fase 1).

### N4 — Dove vanno le memorie

**Conseguenza di N3.** Le memorie non possono più essere iniettate per turno nel system prompt.

**Decisione — le memorie si sdoppiano:**

| | Cosa | Dove | Quando |
|---|---|---|---|
| **Push** | Contesto di apertura: le memorie rilevanti al **primo** messaggio | Nel `system`, **congelato** all'apertura della conversazione | Una volta sola |
| **Pull** | Tool `memory_search(query)` | Chiamato dall'agente quando serve | Su richiesta |

Il contesto di apertura è stabile (mai più ricalcolato per quella conversazione) quindi
cacheable. Tutto il resto lo va a prendere l'agente. È coerente col principio guida: struttura
esplicita al posto di iniezione implicita per turno.

**Alternativa scartata.** Mettere le memorie in coda ai `messages` (dopo il breakpoint) le
renderebbe volatili senza rompere la cache, ma le allontanerebbe dalle istruzioni e le
farebbe ri-pagare a prezzo pieno a ogni turno.

### N5 — La sospensione del loop **non è** una sospensione

**Il problema.** «Il loop si sospende in attesa di `/confirm`» è impreciso.
`ExecuteAgentJob` ha `tries = 1` e un `timeout`: un loop che aspetta dieci minuti o tiene
occupato uno slot worker fino al timeout, o muore male.

**Decisione.** Il loop **termina**. Precisamente:

1. Appende `tool_call` + `confirmation_requested`.
2. Mette la conversazione in `awaiting_confirmation`.
3. Manda il prompt su Telegram con gli **argomenti concreti**.
4. **Esce.** Nessun job appeso, nessuno slot occupato.

Il `/confirm` che arriva dopo — dieci minuti, un riavvio del server, un crash del worker —
appende `confirmation_granted`, rimette `idle`, dispatcha un job nuovo. Il job **riproietta il
log**, esegue il tool pendente, appende `tool_result`, e riparte da lì.

**Il log è lo stato sospeso.** Crash-safe per costruzione, senza serializzare niente in cache.

**Vincolo sulla proiezione.** Un `tool_call` senza `tool_result` è una sequenza **invalida**
per l'API (T5). Quindi al riavvio l'ordine è obbligato: **prima** si esegue il tool pendente e
si appende il risultato, **poi** si proietta e si chiama l'API. Mai proiettare uno stato con un
tool_use scoperto.

### N6 — `execution_logs` e `conversation_events` convivono, con confini netti

**Il problema.** Due log append-only della stessa cosa è un odore.

**Decisione — sono due cose diverse e restano entrambe:**

| | `conversation_events` | `execution_logs` |
|---|---|---|
| Cos'è | **Verità semantica**: cosa è successo nella conversazione | **Traccia operativa**: cosa ha fatto una singola esecuzione del loop |
| Chi lo legge | La proiezione | Il log viewer + lo stream SSE della dashboard |
| Granularità | Per conversazione | Per run |
| Si può cancellare? | **No**, mai | Sì, è archiviabile/purgabile |

`agent_sessions` **non muore**: diventa il record di **un run del loop dentro una
conversazione**. Acquisisce `conversation_id`. Una conversazione ha molti run (uno per
messaggio, più uno per ogni ripresa da conferma). Il log viewer continua a funzionare senza
modifiche.

### N7 — Il generatore diventa un profilo

**Il problema.** `GeneratorService` ha già una sua macchina conversazionale con stato in cache
(`fd_gen:{chatId}`, TTL 15 min) e intercetta i messaggi **prima** del router. Con le
conversazioni di prima classe sono due sistemi che si contendono l'input.

**Decisione.** Il generatore diventa un **profilo** (`generator`) con i suoi tool
(`create_skill_file`, `create_tool_file`, `list_available_tools`). La macchina a stati sparisce:
è l'agente che conduce l'intervista, che è esattamente il compito per cui serve un LLM.
La cache `fd_gen:*` viene rimossa.

**Rimandato a Fase 4**, perché ha senso farlo insieme al registry (i tool di generazione
scrivono file, e la Fase 4 è dove i file generati diventano sicuri).

### N8 — Le immagini non restano in contesto per sempre

**Il problema.** Una foto costa fino a ~4.800 token e la conversazione la rispedisce a ogni
turno. Dieci foto = un prefisso enorme, per sempre.

**Decisione.** La compattazione (Fase 3) **scarta i blocchi immagine** e conserva il testo
dell'analisi. Il path del file locale resta nel riassunto tra i «fatti stabiliti», così
l'agente può ri-analizzare l'immagine con `analyze_image` se serve davvero.

Fino alla Fase 3 il rischio è contenuto: le conversazioni sono corte all'inizio.

### N9 — I pin sono esenti dal riassunto, non dal budget

**Decisione.** Gli eventi pinnati non vengono riassunti, ma **contano** nel calcolo del budget.
Se i soli pin superano una soglia (indicativamente il 50% del budget), il sistema:

1. logga un warning;
2. avvisa l'utente su Telegram: «hai N elementi fissati che occupano X% del contesto»;
3. **non** de-pinna niente automaticamente.

Il de-pin è un'azione dell'utente. Nessuna euristica che decide cosa dimenticare.

### N10 — Ordine delle fasi: C non dipende da A

**Il problema.** Il documento di design metteva A (registry dei tool) per prima, perché «è ciò
che il gate per azione legge».

**Constatazione.** `tools.risk_level`, `tools.risk_category` e `tools.requires_confirmation`
sono **già colonne popolate** (da `DefaultToolsSeeder` + `RiskCategoryMappingSeeder`).
L'orchestrator può fare `Tool::where('name', $toolName)->first()` prima del dispatch e avere
tutto ciò che serve al gate — **sul `ToolExecutor` monolitico di oggi**. Il debito D4 dice
esattamente questo: il cablaggio manca, non i dati.

**Decisione.** L'ordine diventa **Fase 1 (D+E) → Fase 2 (B+C) → Fase 3 (F) → Fase 4 (A)**.

**Perché.** La migrazione dei 58 tool è la parte più lunga e meccanica del piano. Metterla
davanti ritarda di settimane l'unica cosa che l'utente **sente** (il multi-turn), senza
sbloccare niente. L'argomento «non toccare due cose fragili insieme» resta valido, ma vale su
A e il loop — non su A e il gate.

**Precauzione conseguente.** In Fase 2 il loop e `ToolExecutor` vengono toccati entrambi. Per
non violare l'argomento originale, la Fase 2 **non riscrive** `ToolExecutor`: aggiunge solo una
consultazione del model `Tool` prima del dispatch. `ToolExecutor::dispatch()` resta intatto.

---

## 3. Architettura target

### 3.1 Il flusso, dopo

```
Messaggio Telegram
  │
  ├─ TelegramAuthMiddleware                    (invariato)
  │
  ├─ /new      → chiude la conversazione attiva, ne apre una
  ├─ /confirm  → appende confirmation_granted, dispatcha ripresa
  ├─ /deny     → appende confirmation_denied,  dispatcha ripresa
  ├─ /<profilo>→ apre conversazione con quel profilo
  │
  └─ qualsiasi altro messaggio
       │
       ├─ trova o apre la conversazione per questo chat_id
       ├─ APPENDE user_message al log            ← sempre, sicuro
       └─ conversazione idle ? dispatcha job : niente (il loop lo assorbe)

Job (ConversationTurnJob)
  │
  ├─ acquisisce il lock (idle → running); se fallisce, esce
  ├─ esiste un tool_call pendente confermato?
  │     └─ sì → esegui, appendi tool_result
  ├─ LOOP:
  │     ├─ project(eventi) → {system, tools, messages}
  │     ├─ validate(messages)              ← esplode qui, non all'API
  │     ├─ LlmRouter::chat(...)
  │     ├─ appendi assistant_message (rawBlocks verbatim + usage reali)
  │     ├─ nessun tool call ⇒ rispondi su Telegram, BREAK
  │     ├─ per ogni tool call:
  │     │     ├─ appendi tool_call
  │     │     ├─ gate: il tool richiede conferma?
  │     │     │     └─ sì → confirmation_requested, prompt Telegram,
  │     │     │             stato = awaiting_confirmation, EXIT
  │     │     └─ esegui, appendi tool_result
  │     └─ continua
  │
  └─ dentro il lock: ci sono user_message non proiettati?
        ├─ sì → continua il loop
        └─ no → stato = idle, rilascia
```

### 3.2 Il classificatore NL sparisce

`interpretNaturalLanguage()` e `buildNlCatalog()` vengono **rimossi** in Fase 2. Non c'è più
niente da classificare: c'è una conversazione con un profilo e un agente che decide.

Effetto collaterale positivo: sparisce la chiamata LLM extra per ogni messaggio in linguaggio
naturale (punto 9 di [todo.md](todo.md), che si chiude qui).

### 3.3 Cosa resta invariato

- `TelegramAuthMiddleware`, `TelegramParser`, `TelegramService`
- `ToolExecutor` (fino alla Fase 4)
- `LlmRouter`, i tre provider, `SessionSandbox`, `AllowListGuard`
- La dashboard, tranne l'aggiunta della vista conversazioni
- `allowed_commands`: le righe non muoiono, diventano **profili**

---

## 4. Ordine delle fasi e perché

| Fase | Contenuto | Dipende da | Reversibile? |
|---|---|---|---|
| 0 | Baseline, backup, test di regressione | — | n/a |
| 1 | Event sourcing + proiezione + validatore + caching | 0 | **Sì** — codice nuovo, nessun consumatore |
| 2 | Conversazione + gate per azione | 1 | Sì — feature flag |
| 3 | Compattazione | 1, 2 | Sì — se non scatta, il sistema funziona |
| 4 | Registry dei tool + generatore sicuro | — (indipendente) | Sì — fallback sul `match` legacy |

**La Fase 1 non ha consumatori.** Si costruisce tabella, proiettore e validatore, si testano in
isolamento, e il sistema attuale continua a girare senza accorgersene. È il pezzo più
importante ed è quello a rischio zero.

**La Fase 4 è indipendente da tutto** e può partire in parallelo in qualunque momento, se c'è
banda. Non blocca nessuna delle altre.

---

## Fase 0 — Baseline e rete di sicurezza

- [ ] **Fase 0 completata** — branch `v1.0.1`

**Obiettivo.** Non iniziare una riscrittura del core senza sapere cosa funziona adesso e senza
poter tornare indietro.

### 0.1 — Snapshot

- [ ] `php artisan fd:backup --tag=pre-conversation`
- [ ] Verificare che `fd:restore --dry-run` sullo snapshot appena fatto riporti «nothing to
      restore» (cioè che lo snapshot sia coerente col working tree)

### 0.2 — Test di regressione su ciò che esiste

Prima di cambiare il loop serve una rete che dica se si è rotto. Sono i test che
[todo.md](todo.md) punto 13 chiedeva già.

- [ ] `SessionSandboxTest` — `validatePath()` con: path dentro `allowed_base_paths`, fuori,
      dentro `blocked_paths`, dentro la session dir, array `allowed_base_paths` vuoto
- [ ] `CommandRouterTest` — `resolveExecutionMode()`: `Sync`/`Async` espliciti passano; `Auto`
      con timeout ≤30 → `Sync`, >30 → `Async`
- [ ] `TelegramParserTest` — `parseCommand()`: `/comando arg`, `/comando@Bot arg`, testo nudo
- [ ] Rimuovere `tests/Feature/ExampleTest.php` e `tests/Unit/ExampleTest.php`

Tutti senza DB, sul modello di `ConfirmationGateTest` (model non persistiti).

### 0.3 — Misura di partenza

Serve un «prima» per poter dire che il «dopo» è migliore.

- [ ] Annotare in questo file: costo medio per messaggio (token in/out da `agent_sessions`),
      numero di messaggi/giorno, latenza media
- [ ] Annotare quante volte al giorno il classificatore NL viene invocato

### 0.4 — Fissare i due bug bloccanti di todo.md

Non c'entrano con la conversazione, ma sono guasti certi e vanno fatti mentre si è in zona.

- [ ] `generate_xlsx`: `setCellValueByColumnAndRow()` → `setCellValue([$col, $row], $v)`
- [ ] `generate_docx`: `getDefaultFontName/Size()` → `setDefaultFontName/Size()`
- [ ] Modello deprecato `claude-sonnet-4-20250514` nei fallback → `claude-sonnet-5`

### Rituale di fine Fase 0

1. Spuntare le checkbox qui sopra e aggiornare «Stato generale».
2. `codebase_reference.md`: aggiornare **§15** (catalogo test: 3 file nuovi, 2 stub rimossi),
   **§18 D1** (chiuso), **§18 D6** (parzialmente chiuso), **§14.3** (versione modello).
3. Messaggio di fine fase.
4. Commit + push su `v1.0.1`.

---

## Fase 1 — Event sourcing, proiezione, validatore

- [ ] **Fase 1 completata** — branch `v2.0.0`

**Obiettivo.** Costruire il fondamento: il log come verità, la proiezione come funzione pura,
il validatore come asserzione eseguita. **Nessun consumatore**: alla fine di questa fase il
sistema si comporta esattamente come prima.

**Perché prima.** È l'unico pezzo che si può costruire e testare completamente senza toccare
niente di fragile. Ed è quello su cui poggia tutto il resto.

### 1.1 — Schema del database

- [ ] Migration `create_conversations_table`
- [ ] Migration `create_conversation_events_table`
- [ ] Migration `add_conversation_id_to_agent_sessions`

**`conversations`**

| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint AI | PK |
| `telegram_chat_id` | bigint | **INDEX** — si cerca sempre per questo |
| `profile` | varchar(100) | nome del profilo attivo (= `allowed_commands.name`) |
| `status` | enum | `idle \| running \| awaiting_confirmation \| closed` |
| `title` | varchar(255) NULL | per la dashboard; generato dal primo messaggio |
| `last_sequence` | int | ultimo sequence assegnato — **il contatore atomico** |
| `last_projected_sequence` | int NULL | fin dove il loop ha proiettato (per N2) |
| `opened_at` / `closed_at` | timestamp | |
| timestamps | | |

**INDEX composto `(telegram_chat_id, status)`** — è la query calda: «la conversazione attiva
di questa chat».

**`conversation_events`**

| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint AI | PK |
| `conversation_id` | bigint FK | cascade on delete |
| `sequence` | int | **`UNIQUE(conversation_id, sequence)`** |
| `type` | varchar(50) | vedi §1.2 |
| `payload` | longtext | JSON |
| `tokens_input` | int NULL | **usage reali dall'API**, mai stime |
| `tokens_output` | int NULL | idem |
| `cache_read_tokens` | int NULL | per verificare che il caching funzioni (N3) |
| `cache_creation_tokens` | int NULL | idem |
| `is_pinned` | boolean | default false |
| `created_at` | timestamp | |

**INDEX `(conversation_id, sequence)`** — la proiezione legge sempre così.

> ⚠️ Nota su D9 dell'atlante: qui gli indici si mettono **subito**. `conversation_events`
> cresce a ogni turno ed è letta a ogni turno: è esattamente il caso in cui l'indice mancante
> si paga.

### 1.2 — Gli eventi

- [ ] Enum `ConversationEventType`

| Tipo | Payload | Emesso da |
|---|---|---|
| `user_message` | `{text, media?: {type, path}}` | webhook |
| `assistant_message` | `{text, raw_blocks, stop_reason, model, provider}` | loop |
| `tool_call` | `{tool_name, arguments, tool_use_id}` | loop |
| `tool_result` | `{tool_use_id, output, success}` | loop |
| `confirmation_requested` | `{tool_use_id}` | gate |
| `confirmation_granted` | `{tool_use_id}` | webhook `/confirm` |
| `confirmation_denied` | `{tool_use_id, reason?}` | webhook `/deny` |
| `profile_changed` | `{from, to, by: 'user'\|'agent'}` | webhook o tool |
| `compaction` | `{summary, covers_up_to_sequence}` | compattatore |
| `pin` | `{target_sequence}` | tool o dashboard |
| `error` | `{message, context}` | loop |

**`raw_blocks` va salvato verbatim**, così com'è arrivato dall'API. Questo è ciò che risolve
T4 **strutturalmente**: non si ricostruisce mai niente, si rilegge.

### 1.3 — `ConversationEventStore`

- [ ] Classe `App\Services\Conversation\EventStore`

```php
append(Conversation $c, ConversationEventType $type, array $payload, ?Usage $usage = null): ConversationEvent
events(Conversation $c, ?int $fromSequence = null): Collection
lastEvent(Conversation $c, ?ConversationEventType $type = null): ?ConversationEvent
pendingToolCall(Conversation $c): ?ConversationEvent   // tool_call senza tool_result
```

**`append()` è l'unico punto in cui si scrive.** Assegna il sequence dentro una transazione:

```
DB::transaction(function () {
    $c = Conversation::lockForUpdate()->find($id);   // SELECT ... FOR UPDATE
    $seq = ++$c->last_sequence;
    $c->save();
    return ConversationEvent::create([... 'sequence' => $seq ...]);
});
```

Il `UNIQUE(conversation_id, sequence)` è la cintura oltre alle bretelle: se la transazione non
bastasse, si ottiene un errore rumoroso invece di un log corrotto.

### 1.4 — La proiezione (funzione pura)

- [ ] Classe `App\Services\Conversation\ConversationProjector`
- [ ] DTO `ProjectedContext`

```php
final class ConversationProjector
{
    public function project(Collection $events, Profile $profile): ProjectedContext;
}

final class ProjectedContext
{
    public readonly string $system;    // stabile per (profilo, conversazione)
    public readonly array  $tools;     // schemi JSON — stabili per profilo
    public readonly array  $messages;  // ricostruiti dal log
}
```

**Regole di proiezione:**

1. Si parte dall'ultimo evento `compaction` (se esiste): tutto ciò che copre viene sostituito
   dal riassunto come primo messaggio user. Gli eventi anteriori restano nel log ma **non
   entrano nella proiezione**.
2. `user_message` → `{role: user, content: [...]}`.
3. `assistant_message` → `{role: assistant, content: <raw_blocks normalizzati>}`.
   La normalizzazione è quella già esistente in `AgentOrchestrator::normalizeRawBlocks()`
   (array vuoto → `stdClass`, T3): **si sposta qui**, dove appartiene.
4. Tutti i `tool_result` che seguono lo stesso turno assistant → **un solo** messaggio user
   (T5).
5. Gli eventi `pin`, `profile_changed`, `error`, `confirmation_*` **non** producono messaggi:
   sono metadati.
6. Un `tool_call` senza `tool_result` **non viene proiettato** — è stato pendente, non contesto
   (vedi N5).

**Vincolo di caching (N3), da rispettare nella costruzione di `system`:**

```
system = [
    blocco 0: prompt base (resources/prompts/agent_system.md)   ┐
    blocco 1: prompt del profilo                                 │ tutto STABILE
    blocco 2: SKILL.md della skill del profilo                   │ per (profilo, conv.)
    blocco 3: riferimento dei tool                               │
    blocco 4: contesto di memoria congelato all'apertura (N4)    ┘ ← cache_control QUI
]
```

Niente timestamp, niente «data corrente», niente memorie ricalcolate. Se serve la data, la
fornisce un tool.

**Test (senza LLM, senza DB — array di eventi in ingresso, array di messaggi in uscita):**

- [ ] Conversazione semplice user/assistant
- [ ] Turno con una tool call
- [ ] Turno con **tre** tool call parallele → un solo messaggio user con tre `tool_result`
- [ ] Conversazione con `compaction`: il riassunto sostituisce il vecchio, il nuovo resta
- [ ] `tool_call` pendente: **non** compare nella proiezione
- [ ] Eventi `pin`/`profile_changed` non producono messaggi
- [ ] `raw_blocks` con `input: {}` sopravvive come oggetto, non come array

### 1.5 — Il validatore

- [ ] Classe `App\Services\Conversation\MessageSequenceValidator`
- [ ] Eccezione `InvalidMessageSequenceException`

```php
public function validate(array $messages): void;   // void o throw
```

**Invarianti verificate** (le trappole dell'atlante diventano asserzioni eseguite):

1. L'array non è vuoto.
2. Il primo messaggio ha `role: user`.
3. Nessun `tool_use` senza il suo `tool_result` (T5).
4. Ogni `tool_result` ha un `tool_use_id` che corrisponde a un `tool_use` precedente (nessun
   orfano).
5. Tutti i `tool_result` di un turno stanno in **un solo** messaggio user (T5).
6. Nessun `tool_use.input` è un array vuoto — deve essere oggetto (T3).
7. L'ultimo messaggio ha `role: user` (altrimenti la chiamata non ha senso).

**Il validatore gira prima di ogni chiamata API.** Sequenza invalida ⇒ eccezione rumorosa,
run fallito pulito, log intatto. **Mai mandare spazzatura all'API sperando che passi.**

**Test:** un test per invariante, ognuno con la sequenza minima che la viola.

### 1.6 — Prompt caching in `AnthropicProvider`

- [ ] `cache_control: {type: 'ephemeral'}` sull'ultimo blocco `system`
- [ ] Leggere e restituire `cache_read_input_tokens` / `cache_creation_input_tokens` in
      `LlmResponse`
- [ ] Aggiungere i due campi a `LlmResponse`

**Perché qui e non in Fase 2.** Il caching è un vincolo sulla proiezione (N3): va costruito
insieme a lei, non aggiunto dopo. Aggiungerlo dopo significa scoprire che il prefisso è
volatile e rifare la proiezione.

> ⚠️ **Attenzione al modello.** Il prefisso minimo cacheabile è **4096 token su Haiku 4.5**
> (il default attuale in `.env`), **1024 su Sonnet 5**, **512 su Opus 5**. Su Haiku, prompt
> corti non vengono cachati **e non danno errore**: `cache_read_input_tokens` resta a zero.
> Questo va tenuto presente quando si misura, per non concludere che il caching «non
> funziona» quando invece il prompt è semplicemente sotto soglia.

### Definizione di «fatto» per la Fase 1

- Le tre tabelle esistono e sono migrate.
- `EventStore`, `ConversationProjector` e `MessageSequenceValidator` esistono e sono coperti da
  test che girano **senza DB e senza rete**.
- **Nessuna riga del codice esistente è stata modificata** tranne `LlmResponse` e
  `AnthropicProvider` (aggiunta dei campi cache).
- Il bot si comporta esattamente come prima.

### Rituale di fine Fase 1

1. Spuntare le checkbox, aggiornare «Stato generale».
2. `codebase_reference.md`:
   - **§4** — tre tabelle nuove documentate colonna per colonna, con indici
   - **§7** — nuovo blocco `App\Services\Conversation` con tutte le firme
   - **§15** — i test nuovi e **cosa dimostra ciascuno**
   - **§17** — T3/T4/T5 annotate: «asserzione nel validatore da Fase 1»
   - **§18 D6** — copertura aggiornata
   - **§20** — «nessun prompt caching» va tolto
3. Eseguire la **verifica meccanica delle firme** e confrontare con §6–§8.
4. Messaggio di fine fase.
5. Commit + push su `v2.0.0`.

---

## Fase 2 — Conversazione + gate per azione

- [ ] **Fase 2 completata** — branch `v3.0.0`

**Obiettivo.** Il multi-turn. È la fase che l'utente **sente**.

**Perché B e C insieme.** L'autonomia dell'agente nella scelta dei tool (B) è accettabile
**solo** perché il gate è sceso al livello dell'azione concreta (C). L'agente può scegliere il
tool sbagliato, ma non può *fare* la cosa pericolosa sbagliata senza conferma. Spedirle
separate significa avere per un po' un agente autonomo senza il freno che lo rende accettabile.
**Non si separano.**

### 2.1 — I profili

- [ ] Model `Profile` che legge da `allowed_commands` (nessuna migration: si riusa la tabella)
- [ ] Aggiungere `allowed_commands.is_profile` (boolean, default true) — distingue i profili
      dai comandi che non hanno più senso come tali (`help`, `confirm`…)
- [ ] Seeder: definire il **profilo generale**

**Profilo generale — set curato (N1):**

```
memory_search, memory_write, weather, web_search, summarize_url,
generate_qr, switch_profile
```

Piccolo di proposito. Tutto il resto si raggiunge cambiando profilo.

- [ ] Tool `switch_profile(profile)` in `ToolExecutor` (temporaneamente lì; migra in Fase 4)
- [ ] Tool `memory_search(query, limit?)` (N4)

### 2.2 — Ciclo di vita della conversazione

- [ ] Classe `App\Services\Conversation\ConversationManager`

```php
findOrOpenFor(int $telegramChatId, ?string $profile = null): Conversation
close(Conversation $c): void
switchProfile(Conversation $c, string $profile, string $by): void
tryAcquireLock(Conversation $c): bool     // idle → running, atomico
releaseLock(Conversation $c): void
hasUnprojectedMessages(Conversation $c): bool   // per la corsa di N2
```

**`tryAcquireLock()` è la primitiva di N2:**

```php
$affected = Conversation::where('id', $c->id)
    ->where('status', 'idle')
    ->update(['status' => 'running']);
return $affected === 1;
```

Una sola transizione può riuscire. Nessun lock applicativo, nessuna race.

### 2.3 — Il webhook

- [ ] Riscrivere `TelegramWebhookController::handle()`

```
/new         → close() + findOrOpenFor()  → «nuova conversazione»
/<profilo>   → findOrOpenFor(profile)      → apre col profilo richiesto
/confirm     → appende confirmation_granted, idle, dispatch
/deny        → appende confirmation_denied, idle, dispatch
foto / voce  → come oggi (download, Whisper inline) ma il risultato
               diventa un user_message nella conversazione
altro        → append user_message; se idle → dispatch
```

- [ ] **Rimuovere** `interpretNaturalLanguage()`, `buildNlCatalog()`,
      `extractSkillOverview()` (§3.2)
- [ ] **Rimuovere** `PromptBuilder::buildCapabilityCatalog()` (era già codice morto, D5)

### 2.4 — Il loop

- [ ] Nuovo `App\Jobs\ConversationTurnJob`
- [ ] Nuovo `App\Services\Conversation\ConversationOrchestrator`
- [ ] `AgentOrchestrator` resta finché la Fase 2 non è verificata, poi si rimuove

Struttura come da §3.1. I punti che vanno scritti con attenzione:

1. **All'ingresso**: `tryAcquireLock()`; se fallisce, esci in silenzio (un altro loop sta già
   lavorando e assorbirà il messaggio).
2. **Tool pendente confermato**: eseguirlo **prima** di proiettare (N5).
3. **A ogni iterazione**: `project()` → `validate()` → `chat()`. Il validatore prima
   dell'API, sempre.
4. **All'uscita**, dentro il lock: `hasUnprojectedMessages()` ? continua : `releaseLock()`.

### 2.5 — Il gate per azione

- [ ] Estendere `ConfirmationGate`:

```php
requiresConfirmationForTool(Tool $tool): bool
buildToolConfirmationPrompt(Tool $tool, array $arguments): string
```

`requiresConfirmationForTool()` legge `Tool.requires_confirmation` — **la colonna che oggi
nessuno legge** (T9, D4). Il cablaggio mancante *è* questa fase.

**Il prompt mostra gli argomenti concreti.** Non «vuoi eseguire gmail_send?» ma:

```
⚠️ Sto per mandare una mail
A: marco@example.it
Oggetto: Preventivo revisionato
Corpo: Ciao Marco, ti allego la versione…

Categoria di rischio: Messaggio a terzi
/confirm per eseguire · /deny per annullare
```

- [ ] Troncare gli argomenti lunghi (limite Telegram 4096 caratteri) preservando la struttura
- [ ] `htmlspecialchars()` su **tutti** i valori interpolati — sono contenuto generato dal
      modello che finisce in un messaggio `parse_mode: HTML`

> ⚠️ Questo è un punto di sicurezza reale: un argomento che contiene `<b>` o `</code>` può
> rompere il markup, e nel caso peggiore mascherare cosa si sta confermando. Va sanificato.

- [ ] Il `riskCategoryLabel()` attuale (che prende **il primo** tool con categoria non nulla,
      arbitrariamente — D4) viene **rimosso**: adesso la categoria è quella del tool concreto.

### 2.6 — Migrazione e feature flag

- [ ] `FD_CONVERSATION_MODE=true|false` in `.env`, default `false`
- [ ] Con `false`: percorso vecchio identico a oggi
- [ ] Con `true`: percorso nuovo
- [ ] Attivare, usare per qualche giorno, poi rimuovere il flag e il percorso vecchio

**Perché il flag.** Questa fase tocca il webhook e il loop insieme. Se qualcosa va storto in
uso reale, si torna indietro con una variabile d'ambiente invece che con un rollback di git.

### 2.7 — Dashboard

- [ ] Vista `/conversations`: elenco, profilo attivo, stato, numero eventi, token spesi
- [ ] Vista `/conversations/{id}`: il log completo, evento per evento, con payload espandibili
- [ ] Azione «chiudi conversazione»

Serve a **vedere il log**, che è il punto di tutto l'event sourcing: se non si ispeziona, tanto
valeva una tabella messaggi.

### Definizione di «fatto» per la Fase 2

- «Manda una mail a Marco» → «cambia l'oggetto» **funziona**. È il test di accettazione.
- Un tool pericoloso mostra gli argomenti concreti e aspetta.
- `/confirm` dopo un **riavvio del server** riprende correttamente.
- Tre messaggi ravvicinati non corrompono il log (verificabile: sequence contigui, proiezione
  valida).
- `cache_read_input_tokens` > 0 dal secondo turno.

### Rituale di fine Fase 2

1. Checkbox + «Stato generale».
2. `codebase_reference.md`:
   - **§3** — il flusso runtime va **riscritto**: §3.1 e §3.4 non descrivono più il sistema
   - **§7** — `ConversationManager`, `ConversationOrchestrator`; `AgentOrchestrator` rimosso
   - **§8** — `TelegramWebhookController` riscritto, `ConversationTurnJob` nuovo
   - **§9** — rotte dashboard nuove
   - **§13** — i comandi diventano profili
   - **§16** — regola 2 (tool non concessi) va riformulata: il confine adesso è il profilo
     + il gate per azione
   - **§17** — T9 **risolta**; T4/T5 risolte da Fase 1
   - **§18** — D4 **chiuso**
   - **§20** — «nessuna conversazione multi-turno» va tolto
3. Verifica meccanica delle firme.
4. Messaggio di fine fase.
5. Commit + push su `v3.0.0`.

---

## Fase 3 — Compattazione del contesto

- [ ] **Fase 3 completata** — branch `v3.1.0`

**Obiettivo.** Conversazioni lunghe che non esplodono né di costo né di contesto.

**Perché adesso e non prima.** Serve solo quando le conversazioni diventano lunghe — cioè
**dopo** la Fase 2. Ma va fatta **subito dopo**, perché senza di lei la Fase 2 ha una bomba a
orologeria: il prefisso cresce a ogni turno.

**Cosa è architettura e cosa è rifinitura.** §3.1 (punto di taglio come funzione pura) e §3.4
(riassunto sempre dalla fonte) sono **architettura**: metterli dopo significa rifare. §3.3
versione ricca e §3.5 (pinning) sono rifinitura e possono aspettare di vedere cosa si perde
davvero.

### 3.1 — Il punto di taglio (deterministico)

- [ ] `App\Services\Conversation\CutPointFinder`

```php
findCutPoint(Collection $events, int $targetTokens, int $keepLastTurns = 6): ?int
```

**Regole, tutte deterministiche:**

1. Si cammina **a ritroso** accumulando il costo dai campi `tokens_*` reali.
2. Il punto di taglio cade **sempre** subito dopo un `assistant_message` con
   `stop_reason = end_turn` **e senza tool call pendenti**.
3. Mai in mezzo a una coppia `tool_call`/`tool_result`.
4. Mai dentro gli ultimi `keepLastTurns` turni.
5. Mai oltre un `confirmation_requested` non risolto.
6. Gli eventi `is_pinned` non sono riassumibili ma **contano** nel budget (N9).
7. Se non esiste un punto valido ⇒ `null` ⇒ non si compatta (e si logga).

**Test:** un test per regola, più i casi limite (conversazione tutta tool call; conversazione
più corta di `keepLastTurns`; tutto pinnato).

### 3.2 — Quando scatta

- [ ] Calcolo del budget dagli `usage` reali
- [ ] Soglia alta (70%): pianifica un `CompactionJob` **dopo** aver risposto
- [ ] Soglia critica (95%): compattazione **sincrona** prima della chiamata

**Mai compattare in linea prima di rispondere** alla soglia alta: aggiungerebbe una chiamata
LLM di latenza al messaggio dell'utente. La conversazione continua sul contesto pieno finché il
riassunto non è pronto; dal turno dopo la proiezione usa quello.

La soglia critica è il paracadute per la raffica di messaggi che non dà tempo all'asincrona.

- [ ] Budget = finestra di contesto del modello − `max_tokens` − margine (20%)
- [ ] La finestra si legge dal modello, non si hardcoda

### 3.3 — Cosa entra nel riassunto

- [ ] Prompt di compattazione a **sezioni fisse**

«Riassumi questa conversazione» produce prosa narrativa carina e inutile. Sezioni obbligatorie:

1. **Fatti stabiliti** — ID, indirizzi email, path, message_id, decisioni prese
2. **Azioni compiute con esito** — «mail inviata a marco@x.it, oggetto Y, message_id Z», non
   «abbiamo parlato di mail»
3. **Intenzioni aperte** dell'utente
4. **Vincoli dichiarati** — «non usare il tono formale»

**Criterio guida, esplicito nel prompt:** *conserva tutto ciò che un tool futuro potrebbe
rivolere come argomento.* Un `message_id` perso significa che l'agente non può più fare
follow-up su quella mail. È **questo** il costo vero di una compattazione fatta male.

- [ ] I blocchi immagine vengono scartati; resta il testo dell'analisi e il path del file (N8)

### 3.4 — Mai il riassunto del riassunto

- [ ] Il nuovo riassunto si genera **dal log originale** fino al nuovo punto di taglio

Mai riassumendo il riassunto precedente. Ogni riassunto è sempre di **prima generazione**
rispetto alla fonte. La degenerazione progressiva che ammazza questi sistemi diventa
strutturalmente impossibile. Il riassunto vecchio resta nel log come evento storico, ignorato
dalla proiezione.

- [ ] Test: due compattazioni successive; verificare che la seconda legga eventi originali e
      **non** l'evento `compaction` precedente

### 3.5 — Pinning (rimandabile)

- [ ] Tool `context_pin(sequence)` / `context_unpin(sequence)`
- [ ] Azione pin/unpin dalla dashboard
- [ ] Warning quando i pin superano il 50% del budget (N9)

Meccanismo esplicito e ispezionabile contro l'imperfezione inevitabile del riassunto.
«Il preventivo è 4.200 €, non dimenticarlo» → pin → sopravvive verbatim a ogni compattazione.

### Definizione di «fatto» per la Fase 3

- Una conversazione di 100+ turni resta sotto budget.
- Il validatore non fallisce **mai** su una proiezione post-taglio (è la prova che il punto di
  taglio è corretto).
- Un `message_id` presente prima della compattazione è ancora usabile dopo.

### Rituale di fine Fase 3

1. Checkbox + «Stato generale».
2. `codebase_reference.md`: **§7** (`CutPointFinder`, `CompactionService`, `CompactionJob`),
   **§14** (chiavi di configurazione soglie), **§15** (test del punto di taglio).
3. Verifica meccanica delle firme.
4. Messaggio di fine fase.
5. Commit + push su `v3.1.0`.

---

## Fase 4 — Registry dei tool e generatore sicuro

- [ ] **Fase 4 completata** — branch `v4.0.0`

**Obiettivo.** Un file per tool, schema nella classe, generatore che scrive file nuovi invece
di fare chirurgia.

**Indipendente da tutto.** Può partire in parallelo alle fasi 2 e 3 se c'è banda.

### 4.1 — Interfaccia e registry

- [ ] `App\Tools\Contracts\ToolInterface`

```php
interface ToolInterface
{
    public function name(): string;
    public function schema(): array;          // description + properties + required
    public function execute(array $args): string;
    public function riskLevel(): RiskLevel;
    public function riskCategory(): ?RiskCategory;
    public function configKeys(): array;      // chiavi .env richieste
}
```

- [ ] `App\Services\Tools\ToolRegistry` — scansiona `app/Tools/` e `app/Tools/Generated/`
- [ ] Cache del registry in `bootstrap/cache/fd_tools.php`
- [ ] `php artisan fd:tools:refresh` per invalidarla

**Lo schema vive nella classe: unica fonte di verità.** Il disallineamento 58/56/58 (D2, T1)
diventa **strutturalmente impossibile**. La regola §16.7 dell'atlante («tre modifiche
coordinate») sparisce.

- [ ] Gli helper condivisi (`googleOAuthToken`, `gmailAccessToken`, `ensureGeneratedDir`,
      `base64url*`, `extractGmailBody`, `gmailHeader`) diventano trait o service iniettati

### 4.2 — Migrazione dei 58 tool

- [ ] Il dispatch prova prima il registry, poi fa fallback sul `match` legacy
- [ ] Migrare a gruppi, un commit per gruppo (patch version): sistema → rete → dati → telegram
      → email → calendario → todo → spesa → social → documenti → visione → audio
- [ ] `working_memory_append` / `working_memory_read`: migrandoli **si chiude T1/D2** — hanno
      finalmente uno schema visibile al modello
- [ ] A migrazione finita, **rimuovere il ramo legacy** e `ToolExecutor`

Lavoro meccanico: le firme e i parametri di tutti e 58 sono già documentati in
`codebase_reference.md` §11.

### 4.3 — Il generatore scrive file nuovi

- [ ] `WebGeneratorService` e `GeneratorService` producono `app/Tools/Generated/<Nome>.php`
- [ ] **Rimuovere** `insertToolMethod()` e `insertDispatchEntry()`
- [ ] `AIEditorService` modifica un **file intero** — il lavoro per cui i modelli sono bravi —
      invece di fare regex su indentazioni a 4 spazi e tipi di ritorno

**Operazione atomica.** Nel peggiore dei casi si ottiene un file nuovo rotto, mai un
`ToolExecutor` corrotto. **T13 si chiude qui.**

### 4.4 — Guardrail sul codice generato (oggi assenti)

- [ ] `php -l` sul file **prima** di registrarlo: sintassi rotta ⇒ file cestinato, tool mai
      visto dal registry
- [ ] Verifica che la classe implementi davvero `ToolInterface` (reflection)
- [ ] I tool generati nascono `is_active = false`; attivazione **esplicita** da dashboard dopo
      lettura del codice

> Il flusso attuale — scrittura diretta dentro `ToolExecutor.php` e via — è la parte più
> pericolosa dell'intero progetto. Questa sottofase la elimina.

### 4.5 — Il generatore diventa un profilo (N7)

- [ ] Profilo `generator` con i tool di generazione
- [ ] **Rimuovere** la macchina a stati e la cache `fd_gen:*`

L'intervista la conduce l'agente, che è esattamente il compito per cui serve un LLM.

### Definizione di «fatto» per la Fase 4

- `ToolExecutor.php` non esiste più.
- I conteggi dell'atlante (58/56/58) non hanno più senso: c'è **una** lista.
- Un tool generato con sintassi rotta non arriva mai al registry.
- `fd:export-registry` legge dal registry, non dal DB (o dal DB alimentato dal registry).

### Rituale di fine Fase 4

1. Checkbox + «Stato generale».
2. `codebase_reference.md`:
   - **§2** — albero dei file: `app/Tools/` nuovo, `ToolExecutor` rimosso
   - **§7** — `ToolExecutor` sparisce, `ToolRegistry` entra
   - **§11** — **riscritta**: da tabellone a «un file per tool», con l'elenco dei file
   - **§16** — regola 7 (tre modifiche coordinate) **eliminata**
   - **§17** — T1 e T13 **risolte**
   - **§18** — D2 e D8 **chiusi**
   - Il blocco «Manutenzione» in fondo: la verifica dei tre conteggi non serve più
3. Verifica meccanica delle firme.
4. Messaggio di fine fase.
5. Commit + push su `v4.0.0`.

---

## 10. Rischi e rollback

| Rischio | Probabilità | Impatto | Mitigazione |
|---|---|---|---|
| La proiezione produce sequenze invalide | Media | Alto | Il validatore esplode **prima** dell'API; run fallito, log intatto, si corregge la funzione |
| Il caching non si aggancia (prefisso volatile) | **Alta** | Alto sui costi | `cache_read_input_tokens` loggato per evento; diff byte a byte di due prefissi consecutivi |
| L'agente cambia profilo troppo spesso | Media | Medio | `profile_changed` è nel log: si misura. Se accade, il set generale è sbagliato |
| La compattazione perde un `message_id` | Media | Medio | Sezione «fatti stabiliti» + criterio esplicito nel prompt + pinning |
| Due loop sulla stessa conversazione | Bassa | **Alto** | Lock atomico via update condizionale + `UNIQUE(conversation_id, sequence)` |
| Fase 2 rompe l'uso quotidiano | Media | Alto | Feature flag `FD_CONVERSATION_MODE` |
| La migrazione dei 58 tool introduce regressioni | Media | Medio | Fallback sul legacy fino a migrazione completa; un commit per gruppo |

**Rollback per fase:**

- **Fase 1** — nessun consumatore: si può abbandonare senza toccare niente.
- **Fase 2** — `FD_CONVERSATION_MODE=false`.
- **Fase 3** — se la compattazione dà problemi si disattiva la soglia: le conversazioni tornano
  a crescere, il sistema funziona.
- **Fase 4** — il fallback legacy resta finché la migrazione non è completa e verificata.

---

## 11. Cosa NON facciamo in questo piano

Elencato perché l'assenza sia una decisione, non una dimenticanza.

- **Deferred tool loading.** Alternativa più elegante a `switch_profile` (N1), ma legata a
  modelli e beta specifici. Rivalutabile quando `switch_profile` mostrerà i suoi limiti.
- **Conversazioni multiple in parallelo per la stessa chat.** Una chat = una conversazione
  attiva. Il branching è una feature da prodotto, non da agente personale.
- **Streaming delle risposte.** Telegram non ne beneficia molto e complica il loop.
- **Toggle globale di bypass delle conferme.** Era nei desiderata vecchi. Si aggiunge quando il
  gate per azione sarà in uso da abbastanza tempo da sapere quali tool sono davvero noiosi.
  Prima di allora è un modo per disattivare la sicurezza senza dati.
- **Autenticazione della dashboard.** È di [plan_deploy.md](plan_deploy.md), non di qui.
- **Ricerca semantica scalabile** (D10). Le conversazioni non la peggiorano; resta debito
  aperto separato.
- **Notifiche proattive.** `morning_briefing` esiste e nulla lo invoca (§20 dell'atlante).
  Diventa molto più facile dopo questo piano, ma non ne fa parte.

---

## 12. Obiezioni registrate

Conservate perché il controargomento vale anche dove si è deciso diversamente.

**«L'euristica (stickiness, soglie del classificatore) partiva domani sul codice esistente;
questa è una riscrittura del core.»**
Vero. Accettata la riscrittura perché il prodotto è in costruzione e «meglio adesso che in
futuro». Se il sistema andasse in uso critico prima del completamento, l'obiezione torna
valida — motivo per cui ogni fase deve restare abbandonabile a metà.

**«L'agente che sceglie i tool da solo sostituisce un'euristica esplicita e ispezionabile con
una implicita e opaca: quando sbaglia lui, non c'è nemmeno una soglia da regolare.»**
Vero, e accolta come **vincolo di design**: il gate per tool-call protegge esattamente nel punto
in cui gli sbagli contano. Da qui il vincolo B+C-insieme e il fatto che `profile_changed` sia un
evento loggato e non uno stato implicito.

**«L'event sourcing è un cannone contro un passero: una tabella messaggi con "tieni gli ultimi
venti" copre il 90% dei casi con un decimo del codice.»**
Respinta per tre ragioni: (1) il requisito esplicito è il massimo di affidabilità; (2) il
pattern è già padroneggiato (world persistence di Shattered), costo di apprendimento zero;
(3) se FlamingDragon diventa prodotto, il log-come-verità è l'unica strada seria per debuggare
conversazioni di utenti terzi. Il decimo di codice risparmiato si ripaga alla prima
conversazione corrotta senza log da cui ricostruire.

**«Pinning e riassunto a sezioni sono contromisure per fallimenti non ancora osservati.»**
Parzialmente accolta: sono rimandabili (§3.5) perché si aggiungono senza toccare
l'architettura. **Non** rimandabili: punto di taglio come funzione pura testata (§3.1) e
riassunto sempre dalla fonte (§3.4). Quelli sono architettura.

**«Mettere A per ultima ritarda la messa in sicurezza del generatore (T13).»**
Accolta parzialmente. Il generatore resta pericoloso fino alla Fase 4. Mitigazione immediata,
da fare in Fase 0 se lo si usa nel frattempo: `fd:backup --tag=pre-ai-edit` prima di ogni uso
dell'editor AI o del generatore web. Non è una soluzione, è una rete.

---

## Riepilogo dello stato

| Fase | Branch | Stato |
|---|---|---|
| 0 — Baseline | `v1.0.1` | ⬜ |
| 1 — Event sourcing, proiezione, validatore | `v2.0.0` | ⬜ |
| 2 — Conversazione + gate per azione | `v3.0.0` | ⬜ |
| 3 — Compattazione | `v3.1.0` | ⬜ |
| 4 — Registry dei tool | `v4.0.0` | ⬜ |
