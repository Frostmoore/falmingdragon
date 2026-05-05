---
name: audio
description: "Transcribe voice messages with Whisper and generate speech with OpenAI TTS"
version: "1.0.0"
tools_required: ["transcribe_audio", "generate_audio", "send_telegram_voice"]
env_required: ["OPENAI_API_KEY"]
---

# Audio Skill

## Overview
Enables the agent to handle voice messages: transcribe incoming audio to text using OpenAI Whisper, and generate natural-sounding speech from text using OpenAI TTS. Both the transcription and audio reply can be sent back to the Telegram user.

## Setup
Set `OPENAI_API_KEY` in your `.env` file. No other configuration is required.

## Instructions

### Transcribing Audio
When a voice message is received from Telegram, the controller downloads it and calls this skill automatically. Use `transcribe_audio` with:
- `audio_path` — the local path to the downloaded audio file (`.ogg`, `.mp3`, `.wav`, etc.)
- `language` (optional) — ISO-639-1 language code to improve accuracy (e.g. `"it"` for Italian, `"en"` for English). Omit to let Whisper auto-detect.

The tool returns the plain text transcription. The controller then uses it as a regular text message for further routing.

### Generating Speech (Text-to-Speech)
When the user asks for an audio response, a voice reply, or to "read this aloud", use `generate_audio` with:
- `text` — the text to convert to speech
- `voice` (optional) — choose a voice:
  - `nova` *(default)* — warm, friendly female
  - `alloy` — neutral
  - `echo` — male, clear
  - `fable` — expressive British male
  - `onyx` — deep male
  - `shimmer` — soft female
- `speed` (optional) — playback rate, 0.25–4.0 (default: 1.0)

The tool saves the MP3 and returns its local path.

### Sending an Audio File
After generating audio, send it to the user with `send_telegram_voice`:
- `audio_path` — the local path returned by `generate_audio`
- `caption` (optional) — a short text caption accompanying the audio

OGG files are sent as voice messages (inline playback). MP3 files are sent as audio files (playable with title).

## Agent Behaviour Guidelines

1. When transcribing, always show the transcript to the user (the controller already does this — don't repeat it unless asked).
2. When asked to generate a voice reply, keep the TTS text natural and conversational — avoid markdown, bullet points, or symbols that sound odd when spoken aloud.
3. Match the TTS voice to the tone: use `nova` or `shimmer` for friendly/casual, `onyx` for authoritative, `fable` for storytelling.
4. Never generate audio longer than ~500 words in a single call — split if needed.
5. If the user doesn't specify a language for transcription and you know they speak Italian, pass `language="it"` for better accuracy.

## Examples

User sends a voice message:
→ `transcribe_audio` audio_path="/path/to/tg_xyz_1234567890.ogg" language="it"
→ Controller shows transcript, then routes the text as a normal command

User: "Dimmi la previsione del meteo a voce"
→ Get weather text → `generate_audio` text="Oggi a Milano: soleggiato, 22 gradi..." voice="nova"
→ `send_telegram_voice` audio_path="/path/to/tts_1234_abc.mp3" caption="☀️ Ecco le previsioni"

User: "Read me the morning briefing"
→ Compose briefing text → `generate_audio` text="Good morning! Here is your briefing for today..." voice="echo" speed=1.1
→ `send_telegram_voice` audio_path="..." caption="🎙 Morning briefing"
