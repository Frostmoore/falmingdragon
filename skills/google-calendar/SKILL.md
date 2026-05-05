---
name: google-calendar
description: "Read and manage Google Calendar events via the Google Calendar API v3"
version: "1.0.0"
tools_required: ["google_calendar_list", "google_calendar_create", "google_calendar_delete"]
env_required: ["GOOGLE_CLIENT_ID", "GOOGLE_CLIENT_SECRET", "GOOGLE_REFRESH_TOKEN", "GOOGLE_CALENDAR_ID"]
---

# Google Calendar Skill

## Overview
Allows the agent to list, create, and delete events on Google Calendar using the Google Calendar API v3 with OAuth2 refresh-token authentication.

## Setup
1. Go to [Google Cloud Console](https://console.cloud.google.com) → APIs & Services → Enable "Google Calendar API"
2. Create OAuth2 credentials (Desktop app) — copy Client ID and Client Secret
3. Use OAuth Playground to get a refresh token for scope `https://www.googleapis.com/auth/calendar`
4. In FlamingDragon `.env`, set:
   - `GOOGLE_CLIENT_ID=...`
   - `GOOGLE_CLIENT_SECRET=...`
   - `GOOGLE_REFRESH_TOKEN=...`
   - `GOOGLE_CALENDAR_ID=primary` (or specific calendar ID)

## Instructions

### Listing Events
Use `google_calendar_list` to fetch upcoming events. Optionally specify `days_ahead` (default: 7) and `max_results` (default: 10).

### Creating an Event
Use `google_calendar_create` with:
- `title` — event title (required)
- `start` — ISO 8601 datetime, e.g. `2026-04-10T14:00:00`
- `end` — ISO 8601 datetime, e.g. `2026-04-10T15:00:00`
- `description` — optional notes
- `location` — optional location string
- `timezone` — optional IANA timezone (default: `Europe/Rome`)

### Deleting an Event
Use `google_calendar_delete` with:
- `event_id` — the ID returned by `google_calendar_list`

## Examples

User: "What's on my calendar this week?"
Agent: Calls `google_calendar_list` with `days_ahead=7`, formats results.

User: "Schedule a meeting with Marco on Thursday at 3pm for 1 hour"
Agent: Calls `google_calendar_create` with title, start, end computed from context.

User: "Delete the dentist appointment"
Agent: Lists events, finds the dentist event, calls `google_calendar_delete` with its ID.
