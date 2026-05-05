---
name: whatsapp
description: "Send WhatsApp messages via the Meta WhatsApp Business Cloud API"
version: "1.0.0"
tools_required: ["whatsapp_send"]
env_required: ["WHATSAPP_PHONE_NUMBER_ID", "WHATSAPP_ACCESS_TOKEN"]
---

# WhatsApp Skill

## Overview
Sends WhatsApp messages using the Meta WhatsApp Business Cloud API. Requires a WhatsApp Business account and API access token.

## Setup
1. Go to [Meta for Developers](https://developers.facebook.com) → Create App → Business
2. Add "WhatsApp" product to your app
3. In the WhatsApp > Getting Started section:
   - Copy the **Phone Number ID**
   - Generate or use a permanent **System User Access Token** with `whatsapp_business_messaging` permission
4. In FlamingDragon `.env`, set:
   - `WHATSAPP_PHONE_NUMBER_ID=12345678901234`
   - `WHATSAPP_ACCESS_TOKEN=EAAxxxxxxx...`

**Note:** Recipients must have opted in (sent a message first) or you must use approved message templates for outbound.

## Instructions

### Sending a Text Message
Use `whatsapp_send` with:
- `to` — recipient phone number in international format without `+` (e.g. `393331234567`)
- `message` — the text content to send
- `template` — (optional) name of approved template to use instead of free-form text

## Examples

User: "Send 'I'll be late' to Marco on WhatsApp at +39 333 1234567"
→ `whatsapp_send` to="393331234567" message="I'll be late"

User: "WhatsApp mom: dinner at 8pm"
→ Agent recalls mom's number from memory or asks, then calls `whatsapp_send`
