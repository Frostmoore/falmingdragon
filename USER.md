# USER.md — Profilo Utente

**Ultimo aggiornamento:** 2026-05-05

---

## Identità

| Campo | Valore |
|---|---|
| Nome | Ronk |
| Email | nbdy88@gmail.com |
| Lingua preferita | Italiano |
| Fuso orario | Europe/Rome (UTC+1 invernale / UTC+2 estivo) |
| Chat ID Telegram | Definito in `FD_TELEGRAM_ALLOWED_CHAT_IDS` (`.env`) — non scrivere qui |

---

## Preferenze di risposta

- **Lingua:** rispondere sempre in italiano, salvo richiesta esplicita in altra lingua.
- **Lunghezza:** risposta concisa. Niente preamboli lunghi o riepilogo di ciò che si sta per fare.
- **Formato Telegram:** usare grassetto `<b>`, corsivo `<i>`, codice `<code>` e link `<a>`. Evitare Markdown puro (Telegram usa HTML parse_mode).
- **Conferme:** per azioni completate usare un messaggio breve di conferma (es. "✅ Fatto."), non un riepilogo completo.
- **Errori:** segnalare gli errori in modo chiaro ma senza esposizione di stack trace.

---

## Contatti frequenti

> Sezione popolata progressivamente dall'agente via tool `memory_write` con chiave `contact:<nome>`.
> Usare `memory_read key=contact:<nome>` per recuperare dettagli di un contatto.

| Nome | Dettagli |
|---|---|
| — | — |

---

## Preferenze operative

- Il briefing mattutino (se attivato) deve includere: meteo, eventi calendario del giorno, email non lette importanti.
- Le liste della spesa sono in italiano; le unità di misura italiane (kg, l, pz).
- I task todo sono ordinati per priorità; se non specificata, aggiungere in fondo alla lista.
- Le email vengono inviate dall'account Gmail collegato (definito in `.env`).
