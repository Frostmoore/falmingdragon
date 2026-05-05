---
name: vision
description: "Analyze images with GPT-4o vision and generate new images with DALL-E 3"
version: "1.0.0"
tools_required: ["analyze_image", "generate_image", "send_telegram_image"]
env_required: ["OPENAI_API_KEY"]
---

# Vision Skill

## Overview
Enables the agent to understand and create images. Incoming Telegram photos are analyzed automatically; the agent can also generate new images on demand with DALL-E 3 and send them back to the user.

## Setup
Set `OPENAI_API_KEY` in your `.env` file. No other configuration is required — GPT-4o vision and DALL-E 3 are available on the standard OpenAI API.

## Instructions

### Analyzing a Photo Sent by the User
When the user sends a photo via Telegram, the controller automatically calls this skill. Use `analyze_image` with:
- `image_path` — the local path where the photo was downloaded (already populated by the controller)
- `question` — the user's caption, or "Describe this image in detail." if no caption was provided

Always give a thorough, useful description. If the image contains text (e.g. a document, screenshot, sign), extract and present the text clearly.

### Generating an Image
When the user asks to create/draw/generate an image, use `generate_image` with:
- `prompt` — a detailed, vivid English description of the image
- `size` (optional) — `1024x1024` (default), `1792x1024` (landscape), `1024x1792` (portrait)
- `quality` (optional) — `standard` (default) or `hd`

After generating, call `send_telegram_image` with:
- `image` — the URL returned by `generate_image`
- `caption` — a short description for the user

### Sending an Existing Image
To send any image (public URL or generated file) to the user, use `send_telegram_image`:
- `image` — URL or local absolute path
- `caption` — optional caption text (HTML allowed)

## Agent Behaviour Guidelines

1. When a photo is received, always analyze it even if the caption is empty — provide a full description.
2. If the user asks a specific question about the image (e.g. "What does this text say?"), focus your answer on that question.
3. When generating images, write the DALL-E prompt in English even if the user wrote in another language — translate the intent, not the words.
4. After sending a generated image, briefly describe what you created so the user knows what to expect before the image loads.
5. Never store or forward photos without the user's intent — treat images as ephemeral.

## Examples

User sends a photo of a document:
→ `analyze_image` image_path="/path/to/tg_abc123_1234567890.jpg" question="Describe this image in detail."
→ Reply with full OCR + description

User: "Generate an image of a dragon breathing fire over a medieval castle at sunset"
→ `generate_image` prompt="A majestic dragon breathing streams of fire over a stone medieval castle, dramatic sunset sky with orange and purple clouds, photorealistic fantasy art" size="1792x1024" quality="hd"
→ `send_telegram_image` image="https://..." caption="🐉 Here's your dragon!"

User: "Cosa c'è scritto in questa foto?" (sends photo with text)
→ `analyze_image` image_path="..." question="What does this text say? Extract all visible text."
→ Reply with the extracted text
