---
name: gmail
description: "Read, search, and send emails via Gmail using the Gmail API v1"
version: "1.0.0"
tools_required: ["gmail_list", "gmail_read", "gmail_send", "gmail_search", "gmail_mark_read", "gmail_trash"]
env_required: ["GOOGLE_CLIENT_ID", "GOOGLE_CLIENT_SECRET", "GOOGLE_GMAIL_REFRESH_TOKEN"]
---

# Gmail Skill

## Overview
Allows the agent to manage Gmail: reading the inbox, searching emails, reading full messages, composing and sending emails, marking as read, and trashing messages.

## Setup
1. Go to [Google Cloud Console](https://console.cloud.google.com) → APIs & Services → Enable **Gmail API**
2. Use the same OAuth2 credentials as Google Calendar (same Client ID and Secret), OR create new ones
3. Get a refresh token with the **Gmail scope**:
   - Go to [OAuth Playground](https://developers.google.com/oauthplayground)
   - In settings (⚙) check "Use your own OAuth credentials", enter Client ID & Secret
   - In "Select & authorize APIs", type: `https://www.googleapis.com/auth/gmail.modify`
   - Click "Authorize APIs" → sign in → "Exchange authorization code for tokens"
   - Copy the **Refresh token**
4. In FlamingDragon `.env`, set:
   - `GOOGLE_CLIENT_ID=...` (same as Calendar)
   - `GOOGLE_CLIENT_SECRET=...` (same as Calendar)
   - `GOOGLE_GMAIL_REFRESH_TOKEN=...` ← Gmail-specific token
   
**Note:** If you want a single token for both Calendar + Gmail, you can request both scopes at once in OAuth Playground and use the same token for `GOOGLE_REFRESH_TOKEN` and `GOOGLE_GMAIL_REFRESH_TOKEN`.

## Instructions

### Listing Emails
Use `gmail_list` with:
- `query` — Gmail search string (default: `in:inbox`). Examples:
  - `is:unread in:inbox` — unread inbox messages
  - `in:inbox newer_than:1d` — last 24 hours
  - `from:boss@company.com` — from a specific sender
- `max_results` — number of messages to return (default: 10, max: 50)

### Reading an Email
Use `gmail_read` with:
- `message_id` — the ID from `gmail_list` or `gmail_search`

This also marks the message as read automatically.

### Searching Emails
Use `gmail_search` with:
- `query` — Gmail search syntax:
  - `from:alice@example.com subject:invoice`
  - `has:attachment larger:1M`
  - `after:2026/04/01 before:2026/04/07`
  - `label:important is:unread`

### Sending an Email
Use `gmail_send` with:
- `to` — recipient address (required)
- `subject` — email subject (required)
- `body` — email text body (required)
- `cc` — optional CC address
- `bcc` — optional BCC address

### Marking as Read
Use `gmail_mark_read` with:
- `message_id` — message to mark as read

### Trashing an Email
Use `gmail_trash` with:
- `message_id` — message to move to trash

## Agent Behaviour Guidelines

1. When the user says "check my email" or "show my inbox", use `gmail_list` with `query="in:inbox"` and show a summary.
2. When the user asks about a specific email (e.g. "read the invoice from Marco"), use `gmail_search` to find it, then `gmail_read` for the full content.
3. When composing replies, extract the original sender and subject from `gmail_read`, prefix subject with "Re: " if not already present.
4. Never trash an email without explicit user confirmation.
5. Summarise long emails — don't dump the full body unless the user asks.
6. Remember frequently contacted addresses using `memory_write` (namespace: `contacts`).

## Examples

User: "Show my unread emails"
→ `gmail_list` query="is:unread in:inbox" max_results=10

User: "Read email from Marco about the project"
→ `gmail_search` query="from:marco subject:project" → `gmail_read` message_id=...

User: "Reply to Marco's last email saying I'll send the file tomorrow"
→ `gmail_search` query="from:marco" max_results=1 → `gmail_read` → `gmail_send` to=marco@... subject="Re: ..." body="..."

User: "Delete the spam from no-reply@newsletter.com"
→ `gmail_search` query="from:no-reply@newsletter.com" → confirm with user → `gmail_trash` for each
