---
name: social-media
description: "Post to Facebook Pages and Instagram Business accounts via Meta Graph API"
version: "1.0.0"
tools_required: ["facebook_post", "instagram_post", "facebook_feed"]
env_required: ["FACEBOOK_PAGE_ACCESS_TOKEN", "FACEBOOK_PAGE_ID", "INSTAGRAM_BUSINESS_ACCOUNT_ID"]
---

# Social Media Skill

## Overview
Allows the agent to post content to Facebook Pages and Instagram Business accounts using the Meta Graph API v19.

## Setup
1. Go to [Meta for Developers](https://developers.facebook.com) → Your App → Graph API Explorer
2. Select your App, generate a **Page Access Token** with permissions:
   - `pages_manage_posts`, `pages_read_engagement` (Facebook)
   - `instagram_basic`, `instagram_content_publish` (Instagram)
3. Get your Page ID from your Facebook Page settings
4. Get your Instagram Business Account ID (linked to the page) from the Graph API:
   `GET /me/accounts` then `GET /{page-id}?fields=instagram_business_account`
5. In FlamingDragon `.env`, set:
   - `FACEBOOK_PAGE_ACCESS_TOKEN=EAAxxxxxxx...`
   - `FACEBOOK_PAGE_ID=123456789`
   - `INSTAGRAM_BUSINESS_ACCOUNT_ID=987654321`

**Note:** For long-lived tokens (60 days), exchange short-lived tokens via the token debug tool.

## Instructions

### Posting to Facebook
Use `facebook_post` with:
- `message` — the post text (required)
- `link` — optional URL to attach
- `image_url` — optional public image URL to include

### Posting to Instagram
Use `instagram_post` with:
- `image_url` — publicly accessible image URL (required)
- `caption` — post caption/text

### Reading Facebook Feed
Use `facebook_feed` with:
- `limit` — number of recent posts to fetch (default: 5)

## Examples

User: "Post on Facebook: 'New product just launched! Check it out.'"
→ `facebook_post` message="New product just launched! Check it out."

User: "Post this photo to Instagram with caption 'Golden hour in Milan'"
→ `instagram_post` image_url="..." caption="Golden hour in Milan"

User: "What are my last 3 Facebook posts?"
→ `facebook_feed` limit=3
