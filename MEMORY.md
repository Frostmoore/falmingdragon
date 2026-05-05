# MEMORY.md — Guida alla Memoria Contestuale

**Ultimo aggiornamento:** 2026-05-05

---

## Struttura del DB

I dati reali sono nella tabella `memory` (MariaDB). Questo file è la guida operativa per l'agente.

```sql
CREATE TABLE memory (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  key        VARCHAR(255) NOT NULL UNIQUE,
  value      TEXT NOT NULL,
  embedding  JSON NULL,          -- vettore float[] OpenAI text-embedding-3-small (1536 dim)
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
```

---

## Come usare la memoria

### Leggere un valore esatto
```
tool: memory_read
args: { key: "user:nome" }
```

### Scrivere / aggiornare
```
tool: memory_write
args: { key: "user:nome", value: "Mario" }
```
Se la chiave esiste già, viene aggiornata (upsert). L'embedding viene ricalcolato automaticamente.

### Ricerca semantica
`MemoryService` usa `EmbeddingService::generate()` + cosine similarity per trovare le voci più rilevanti al contesto corrente. La ricerca semantica è usata implicitamente dal sistema di retrieval, non richiede un tool separato.

---

## Convenzione delle chiavi

| Prefisso | Scopo | Esempio |
|---|---|---|
| `user:` | Informazioni sull'utente | `user:nome`, `user:preferenze` |
| `contact:` | Contatti personali | `contact:mario_rossi` |
| `fact:` | Fatti generali da ricordare | `fact:password_wifi` |
| `system:` | **Riservato** a `fd:sync-embeddings` — embedding dei file .md di sistema | `system:FLAMINGDRAGON.md` |

> ⚠️ Non usare mai il prefisso `system:` per memorizzare dati utente — viene sovrascritto da `fd:sync-embeddings`.

---

## Policy di retention

- Nessuna scadenza automatica sulle voci.
- Le voci vengono aggiornate in-place tramite la chiave (upsert).
- Per eliminare una voce: `memory_write key=<chiave> value=""` (azzeramento) oppure richiesta diretta a DB.

---

## Embedding e retrieval semantico

- Motore: OpenAI `text-embedding-3-small` (1536 dimensioni)
- Similarithy: cosine similarity calcolata localmente in `EmbeddingService::cosineSimilarity()`
- Disponibilità: solo se `OPENAI_API_KEY` è configurata in `.env`
- Se embedding non disponibile: il retrieval cade in modalità exact-match per chiave

---

## Sync con file .md di sistema

Il comando `fd:sync-embeddings` legge i 6 file `.md` di sistema e crea/aggiorna le voci `system:<filename>` con l'embedding del contenuto completo. Questo permette all'agente di trovare informazioni di sistema tramite ricerca semantica.

Eseguire dopo ogni modifica ai file `.md` di sistema:
```
php artisan fd:sync-embeddings
```
Oppure per un singolo file:
```
php artisan fd:sync-embeddings --file=FLAMINGDRAGON.md
```
