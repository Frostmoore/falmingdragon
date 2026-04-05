@extends('layouts.app')
@section('title', 'Setup Wizard')

@section('content')
<div x-data="wizard()" class="max-w-3xl mx-auto">

    {{-- ------------------------------------------------------------------ --}}
    {{-- Header + Progress                                                    --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="mb-8">
        <h1 class="text-xl font-bold text-white mb-1">Configurazione guidata di FlamingDragon</h1>
        <p class="text-sm text-gray-500">Segui i passi per configurare tutto correttamente. Puoi tornare indietro in qualsiasi momento.</p>
    </div>

    {{-- Progress bar --}}
    <div class="mb-8">
        <div class="flex items-center gap-1">
            <template x-for="(step, idx) in steps" :key="idx">
                <div class="flex items-center">
                    <button @click="goToStep(idx)"
                            :disabled="idx > maxReached"
                            class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold transition-all"
                            :class="{
                                'bg-brand text-white shadow-lg shadow-orange-900/30': currentStep === idx,
                                'bg-green-700 text-white': idx < currentStep,
                                'bg-gray-700 text-gray-500': idx > currentStep,
                                'cursor-not-allowed opacity-50': idx > maxReached,
                                'cursor-pointer hover:bg-gray-600': idx <= maxReached && idx !== currentStep,
                            }">
                        <span x-show="idx < currentStep">✓</span>
                        <span x-show="idx >= currentStep" x-text="idx + 1"></span>
                    </button>
                    <div x-show="idx < steps.length - 1"
                         class="h-0.5 w-8"
                         :class="idx < currentStep ? 'bg-green-700' : 'bg-gray-700'">
                    </div>
                </div>
            </template>
        </div>
        <div class="mt-2 flex gap-1">
            <template x-for="(step, idx) in steps" :key="idx">
                <div class="text-xs truncate" style="width: 4.5rem;"
                     :class="currentStep === idx ? 'text-brand' : 'text-gray-600'"
                     x-text="step.shortLabel">
                </div>
            </template>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Step card                                                            --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">

        {{-- Step header --}}
        <div class="px-6 py-4 border-b border-gray-800 flex items-center gap-3">
            <span class="text-2xl" x-text="steps[currentStep].icon"></span>
            <div>
                <h2 class="text-base font-semibold text-white" x-text="steps[currentStep].label"></h2>
                <p class="text-xs text-gray-500" x-text="steps[currentStep].subtitle"></p>
            </div>
            <div class="ml-auto text-xs text-gray-600">
                Passo <span x-text="currentStep + 1"></span> di <span x-text="steps.length"></span>
            </div>
        </div>

        {{-- Step body --}}
        <div class="px-6 py-6 space-y-6">

            {{-- ============================================================ --}}
            {{-- STEP 0: Benvenuto                                            --}}
            {{-- ============================================================ --}}
            <div x-show="currentStep === 0" x-cloak>
                <div class="prose prose-invert prose-sm max-w-none space-y-4">
                    <p class="text-gray-300">
                        Questo wizard ti guida nella configurazione completa di FlamingDragon passo per passo.
                        Al termine avrai un agente AI personale funzionante, controllabile da Telegram.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                        @php
                            $checks = [
                                ['label' => 'Database',         'ok' => true,             'detail' => 'MariaDB connesso, migrazioni eseguite'],
                                ['label' => 'Tabelle DB',       'ok' => true,             'detail' => '10 tabelle create + dati di default'],
                                ['label' => 'Token Telegram',   'ok' => !empty($botToken),  'detail' => empty($botToken) ? 'Da configurare' : 'Impostato'],
                                ['label' => 'Provider LLM',    'ok' => !empty($anthropicKey) || !empty($openaiKey), 'detail' => (!empty($anthropicKey) || !empty($openaiKey)) ? 'Almeno uno configurato' : 'Da configurare'],
                                ['label' => 'Queue Driver',     'ok' => $queueDriver !== 'sync', 'detail' => $queueDriver],
                                ['label' => 'Webhook',          'ok' => false,            'detail' => 'Da verificare'],
                            ];
                        @endphp
                        @foreach($checks as $check)
                            <div class="flex items-start gap-3 bg-gray-800 rounded-lg px-4 py-3">
                                <span class="text-lg mt-0.5">{{ $check['ok'] ? '✅' : '⚠️' }}</span>
                                <div>
                                    <div class="text-sm font-medium {{ $check['ok'] ? 'text-green-400' : 'text-yellow-400' }}">
                                        {{ $check['label'] }}
                                    </div>
                                    <div class="text-xs text-gray-500">{{ $check['detail'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="bg-blue-950 border border-blue-800 rounded-lg p-4 text-xs text-blue-300 mt-4">
                        <strong>Cosa ti servirà:</strong>
                        <ul class="mt-2 space-y-1 list-disc list-inside">
                            <li>Un bot Telegram (creato tramite <strong>@BotFather</strong>)</li>
                            <li>Il tuo <strong>Telegram Chat ID</strong> (lo ottieni con @userinfobot)</li>
                            <li>Una chiave API del provider LLM scelto (Anthropic, OpenAI, oppure Ollama locale)</li>
                            <li>Un URL pubblico HTTPS raggiungibile da Telegram per il webhook (in produzione)</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- STEP 1: Telegram Bot Token                                   --}}
            {{-- ============================================================ --}}
            <div x-show="currentStep === 1" x-cloak>
                <div class="space-y-5">

                    <div class="bg-gray-800 rounded-lg p-4 text-xs text-gray-300 space-y-3">
                        <p class="font-semibold text-white">Come creare un bot Telegram:</p>
                        <ol class="list-decimal list-inside space-y-2 text-gray-400">
                            <li>Apri Telegram e cerca <code class="text-brand">@BotFather</code></li>
                            <li>Invia il comando <code class="text-brand">/newbot</code></li>
                            <li>Scegli un <strong>nome</strong> per il bot (es. "Il mio DragonBot")</li>
                            <li>Scegli un <strong>username</strong> univoco che termini con <code>bot</code> (es. <code>mydragon_bot</code>)</li>
                            <li>BotFather ti risponderà con il <strong>token API</strong> — copialo qui sotto</li>
                        </ol>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-2">
                            Token del bot <span class="text-red-400">*</span>
                        </label>
                        <input type="text"
                               x-model="form.botToken"
                               placeholder="1234567890:ABCDefghijklmnopqrstuvwxyz_1234567"
                               class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-sm font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand">
                        <p class="text-xs text-gray-600 mt-1">Il token ha il formato <code>numerici:stringa_alfanumerica</code></p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-2">
                            Webhook Secret (opzionale ma consigliato)
                        </label>
                        <div class="flex gap-2">
                            <input type="text"
                                   x-model="form.webhookSecret"
                                   placeholder="una-stringa-segreta-casuale"
                                   class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-sm font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-brand">
                            <button @click="form.webhookSecret = randomSecret()"
                                    class="px-3 py-2 bg-gray-700 hover:bg-gray-600 text-xs text-gray-300 rounded-lg transition">
                                Genera
                            </button>
                        </div>
                        <p class="text-xs text-gray-600 mt-1">
                            Aggiunge un livello di sicurezza al webhook. Telegram invierà questo valore nell'header
                            <code>X-Telegram-Bot-Api-Secret-Token</code> ad ogni richiesta.
                        </p>
                    </div>

                    {{-- Test button --}}
                    <div class="flex items-center gap-3">
                        <button @click="testTelegram()"
                                :disabled="!form.botToken || testing"
                                class="px-4 py-2 bg-blue-700 hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm rounded-lg transition flex items-center gap-2">
                            <span x-show="!testing">Verifica token</span>
                            <span x-show="testing" class="animate-pulse">Verifica in corso…</span>
                        </button>
                        <div x-show="telegramTestResult" x-cloak>
                            <span x-show="telegramTestResult?.success" class="text-green-400 text-sm">
                                ✓ Bot: <strong x-text="telegramTestResult?.bot_name"></strong>
                                (<code x-text="'@' + telegramTestResult?.username"></code>)
                            </span>
                            <span x-show="!telegramTestResult?.success" class="text-red-400 text-sm">
                                ✗ <span x-text="telegramTestResult?.message"></span>
                            </span>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- STEP 2: Chat ID                                              --}}
            {{-- ============================================================ --}}
            <div x-show="currentStep === 2" x-cloak>
                <div class="space-y-5">

                    <div class="bg-gray-800 rounded-lg p-4 text-xs text-gray-300 space-y-3">
                        <p class="font-semibold text-white">Come ottenere il tuo Telegram Chat ID:</p>
                        <ol class="list-decimal list-inside space-y-2 text-gray-400">
                            <li>Apri Telegram e cerca <code class="text-brand">@userinfobot</code></li>
                            <li>Invia qualsiasi messaggio (anche solo <code>/start</code>)</li>
                            <li>Il bot ti risponderà con il tuo <strong>Id</strong> — è un numero intero (es. <code>82472920</code>)</li>
                            <li>Copia quel numero e incollalo qui sotto</li>
                        </ol>
                        <div class="mt-3 p-3 bg-yellow-950 border border-yellow-800 rounded text-yellow-300">
                            ⚠️ <strong>Sicurezza critica:</strong> Solo gli ID inseriti qui potranno inviare comandi a FlamingDragon.
                            Chiunque non sia in questa lista verrà ignorato silenziosamente.
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-2">
                            Telegram Chat ID(s) <span class="text-red-400">*</span>
                        </label>
                        <input type="text"
                               x-model="form.allowedChatIds"
                               placeholder="82472920"
                               class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-sm font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-brand">
                        <p class="text-xs text-gray-600 mt-1">
                            Inserisci uno o più ID separati da virgola. Es: <code>82472920,123456789</code>
                        </p>
                    </div>

                    <div class="bg-gray-800 rounded-lg p-4 text-xs text-gray-400">
                        <p class="text-white font-semibold mb-2">Perché un solo utente?</p>
                        <p>FlamingDragon è progettato per un <strong>singolo utente</strong>. L'autenticazione avviene
                        esclusivamente tramite la lista di Chat ID nel file <code>.env</code> — non esiste un sistema di
                        login applicativo. Questo è intenzionale: la semplicità è una funzionalità di sicurezza.</p>
                    </div>

                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- STEP 3: Provider LLM                                        --}}
            {{-- ============================================================ --}}
            <div x-show="currentStep === 3" x-cloak>
                <div class="space-y-5">

                    <div class="bg-gray-800 rounded-lg p-4 text-xs text-gray-400 space-y-2">
                        <p class="text-white font-semibold">Scegli il provider LLM predefinito</p>
                        <p>FlamingDragon supporta tre provider. Puoi cambiarli in qualsiasi momento e usare provider
                        diversi per comandi diversi. Il provider predefinito viene usato per tutti i comandi che non
                        hanno un override specifico.</p>
                    </div>

                    {{-- Provider selector --}}
                    <div class="grid grid-cols-3 gap-3">
                        <template x-for="p in llmProviders" :key="p.id">
                            <button @click="form.llmProvider = p.name"
                                    class="border rounded-lg p-4 text-left transition"
                                    :class="form.llmProvider === p.name
                                        ? 'border-brand bg-orange-950/30'
                                        : 'border-gray-700 bg-gray-800 hover:border-gray-600'">
                                <div class="text-sm font-semibold text-white mb-1" x-text="p.display_name"></div>
                                <div class="text-xs text-gray-500" x-text="p.default_model"></div>
                                <div class="mt-2 text-xs" :class="isProviderConfigured(p) ? 'text-green-400' : 'text-yellow-500'">
                                    <span x-show="isProviderConfigured(p)">✓ Chiave configurata</span>
                                    <span x-show="!isProviderConfigured(p)">⚠ Chiave mancante</span>
                                </div>
                            </button>
                        </template>
                    </div>

                    {{-- Model selector --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-2">Modello predefinito</label>

                        {{-- Anthropic models --}}
                        <div x-show="form.llmProvider === 'anthropic'">
                            <select x-model="form.llmModel"
                                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-sm font-mono text-gray-200 focus:outline-none focus:border-brand">
                                <optgroup label="Claude 4 (latest)">
                                    <option value="claude-opus-4-6">claude-opus-4-6 — massima intelligenza</option>
                                    <option value="claude-sonnet-4-6">claude-sonnet-4-6 — bilanciato (default)</option>
                                </optgroup>
                                <optgroup label="Claude Haiku (veloce &amp; economico)">
                                    <option value="claude-haiku-4-5-20251001">claude-haiku-4-5 — veloce &amp; economico</option>
                                </optgroup>
                                <optgroup label="Claude 3.5">
                                    <option value="claude-3-5-sonnet-20241022">claude-3-5-sonnet-20241022</option>
                                    <option value="claude-3-5-haiku-20241022">claude-3-5-haiku-20241022</option>
                                </optgroup>
                                <optgroup label="Claude 3">
                                    <option value="claude-3-opus-20240229">claude-3-opus-20240229</option>
                                </optgroup>
                            </select>
                        </div>

                        {{-- OpenAI models --}}
                        <div x-show="form.llmProvider === 'openai'">
                            <select x-model="form.llmModel"
                                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-sm font-mono text-gray-200 focus:outline-none focus:border-brand">
                                <optgroup label="GPT-4o">
                                    <option value="gpt-4o">gpt-4o — più capace</option>
                                    <option value="gpt-4o-mini">gpt-4o-mini — veloce &amp; economico</option>
                                </optgroup>
                                <optgroup label="GPT-4">
                                    <option value="gpt-4-turbo">gpt-4-turbo</option>
                                    <option value="gpt-4">gpt-4</option>
                                </optgroup>
                                <optgroup label="GPT-3.5">
                                    <option value="gpt-3.5-turbo">gpt-3.5-turbo — economico</option>
                                </optgroup>
                            </select>
                        </div>

                        {{-- Ollama: free text (depends on locally pulled models) --}}
                        <div x-show="form.llmProvider === 'ollama'">
                            <input type="text" x-model="form.llmModel"
                                   placeholder="es. llama3, mistral, phi3"
                                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-sm font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-brand">
                            <p class="mt-1 text-xs text-gray-500">Inserisci il nome esatto del modello scaricato con <code>ollama pull</code></p>
                        </div>
                    </div>

                    {{-- Anthropic --}}
                    <div x-show="form.llmProvider === 'anthropic'" class="space-y-3">
                        <div class="bg-gray-800 rounded-lg p-4 text-xs text-gray-400 space-y-2">
                            <p class="text-white font-semibold">Come ottenere la chiave Anthropic:</p>
                            <ol class="list-decimal list-inside space-y-1">
                                <li>Vai su <code class="text-brand">console.anthropic.com</code></li>
                                <li>Vai in <strong>API Keys</strong> → <strong>Create Key</strong></li>
                                <li>Copia la chiave (inizia con <code>sk-ant-</code>)</li>
                            </ol>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-2">Anthropic API Key</label>
                            <input type="password" x-model="form.anthropicKey"
                                   placeholder="sk-ant-api03-..."
                                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-sm font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-brand">
                        </div>
                    </div>

                    {{-- OpenAI --}}
                    <div x-show="form.llmProvider === 'openai'" class="space-y-3">
                        <div class="bg-gray-800 rounded-lg p-4 text-xs text-gray-400 space-y-2">
                            <p class="text-white font-semibold">Come ottenere la chiave OpenAI:</p>
                            <ol class="list-decimal list-inside space-y-1">
                                <li>Vai su <code class="text-brand">platform.openai.com/api-keys</code></li>
                                <li>Clicca <strong>Create new secret key</strong></li>
                                <li>Copia la chiave (inizia con <code>sk-</code>)</li>
                            </ol>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-2">OpenAI API Key</label>
                            <input type="password" x-model="form.openaiKey"
                                   placeholder="sk-..."
                                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-sm font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-brand">
                        </div>
                    </div>

                    {{-- Ollama --}}
                    <div x-show="form.llmProvider === 'ollama'" class="space-y-3">
                        <div class="bg-gray-800 rounded-lg p-4 text-xs text-gray-400 space-y-2">
                            <p class="text-white font-semibold">Ollama — LLM locale (nessun costo, nessuna chiave API)</p>
                            <ol class="list-decimal list-inside space-y-1">
                                <li>Installa Ollama: <code class="text-brand">curl -fsSL https://ollama.ai/install.sh | sh</code></li>
                                <li>Scarica un modello: <code class="text-brand">ollama pull llama3</code></li>
                                <li>Ollama gira di default su <code>http://localhost:11434</code></li>
                            </ol>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-2">URL Ollama</label>
                            <input type="text" x-model="form.ollamaUrl"
                                   placeholder="http://localhost:11434"
                                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-sm font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-brand">
                        </div>
                    </div>

                    {{-- Test LLM --}}
                    <div class="flex items-center gap-3 pt-2">
                        <button @click="testLlm()"
                                :disabled="testing"
                                class="px-4 py-2 bg-purple-800 hover:bg-purple-700 disabled:opacity-50 text-white text-sm rounded-lg transition flex items-center gap-2">
                            <span x-show="!testing">Testa connessione LLM</span>
                            <span x-show="testing" class="animate-pulse">Test in corso…</span>
                        </button>
                        <div x-show="llmTestResult" x-cloak class="text-sm">
                            <span x-show="llmTestResult?.success" class="text-green-400">
                                ✓ Risposta: "<span x-text="llmTestResult?.response"></span>" (<span x-text="llmTestResult?.tokens"></span> token)
                            </span>
                            <span x-show="!llmTestResult?.success" class="text-red-400">
                                ✗ <span x-text="llmTestResult?.message"></span>
                            </span>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- STEP 4: Queue Worker                                         --}}
            {{-- ============================================================ --}}
            <div x-show="currentStep === 4" x-cloak>
                <div class="space-y-5">

                    <div class="bg-gray-800 rounded-lg p-4 text-xs text-gray-400 space-y-3">
                        <p class="text-white font-semibold">Perché serve un queue worker?</p>
                        <p>FlamingDragon esegue i comandi lenti (deploy, script lunghi) in modo
                        <strong>asincrono</strong> tramite la coda di Laravel. Senza un worker attivo, questi
                        comandi rimarranno in attesa indefinitamente. Il worker gira in background e processa i
                        job man mano che arrivano.</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-sm font-semibold text-white mb-3">Metodo 1 — Avvio manuale (sviluppo)</p>
                            <div class="bg-gray-950 rounded-lg p-4 font-mono text-xs text-green-400">
                                php artisan fd:worker
                            </div>
                            <p class="text-xs text-gray-600 mt-2">Tienilo aperto in un terminale durante lo sviluppo.</p>
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-white mb-3">Metodo 2 — Supervisord (produzione Ubuntu)</p>
                            <div class="bg-gray-950 rounded-lg p-4 font-mono text-xs text-green-400 whitespace-pre">[program:flamingdragon-worker]
command=php {{ base_path('artisan') }} fd:worker
directory={{ base_path() }}
user=ubuntu
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/flamingdragon-worker.log</div>
                            <p class="text-xs text-gray-600 mt-2">
                                Salva in <code>/etc/supervisor/conf.d/flamingdragon.conf</code>, poi:
                                <code>sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start flamingdragon-worker</code>
                            </p>
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-white mb-3">Metodo 3 — Scheduler per heartbeat (cron)</p>
                            <div class="bg-gray-950 rounded-lg p-4 font-mono text-xs text-green-400">* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</div>
                            <p class="text-xs text-gray-600 mt-2">
                                Aggiungi questa riga al crontab dell'utente server (<code>crontab -e</code>).
                                Il comando <code>fd:heartbeat</code> verrà eseguito ogni 30 minuti per pulizia e manutenzione.
                            </p>
                        </div>
                    </div>

                    <div class="bg-blue-950 border border-blue-800 rounded-lg p-4 text-xs text-blue-300">
                        <strong>Nota:</strong> Per lo sviluppo locale con XAMPP, avvia il worker manualmente in un terminale
                        separato prima di testare i comandi asincroni.
                    </div>

                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- STEP 5: Webhook Telegram                                     --}}
            {{-- ============================================================ --}}
            <div x-show="currentStep === 5" x-cloak>
                <div class="space-y-5">

                    <div class="bg-gray-800 rounded-lg p-4 text-xs text-gray-400 space-y-3">
                        <p class="text-white font-semibold">Come funziona il webhook Telegram?</p>
                        <p>Ogni volta che scrivi al bot, Telegram invia una richiesta HTTP POST al tuo server all'URL del webhook.
                        FlamingDragon processa la richiesta, esegue il comando, e risponde via Telegram API.</p>
                        <p class="text-yellow-400">
                            ⚠️ Il webhook richiede un URL <strong>HTTPS pubblico</strong>.
                            Su XAMPP locale puoi usare <code>ngrok</code> per creare un tunnel temporaneo.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-2">URL Webhook (HTTPS pubblico)</label>
                        <input type="url"
                               x-model="form.webhookUrl"
                               placeholder="https://tuodominio.com/api/telegram/webhook"
                               class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-sm font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-brand">
                        <p class="text-xs text-gray-600 mt-1">
                            URL locale rilevato: <code class="text-gray-500">{{ url('/api/telegram/webhook') }}</code>
                            (non raggiungibile da Telegram — sostituisci con il tuo dominio pubblico)
                        </p>
                    </div>

                    <div class="bg-gray-800 rounded-lg p-4 text-xs space-y-2">
                        <p class="text-white font-semibold">Come usare ngrok in sviluppo:</p>
                        <ol class="list-decimal list-inside space-y-1 text-gray-400">
                            <li>Scarica e installa ngrok da <code class="text-brand">ngrok.com</code></li>
                            <li>Avvia il tunnel: <code class="text-brand">ngrok http {{ parse_url(config('app.url'), PHP_URL_PORT) ?? 80 }}</code></li>
                            <li>Copia l'URL <code>https://xxxx.ngrok.io</code> e usalo come base per il webhook</li>
                            <li>L'URL completo sarà: <code>https://xxxx.ngrok.io/api/telegram/webhook</code></li>
                        </ol>
                    </div>

                    <div class="flex items-center gap-3">
                        <button @click="registerWebhook()"
                                :disabled="!form.webhookUrl || !form.botToken || testing"
                                class="px-4 py-2 bg-green-700 hover:bg-green-600 disabled:opacity-50 text-white text-sm rounded-lg transition flex items-center gap-2">
                            <span x-show="!testing">Registra webhook</span>
                            <span x-show="testing" class="animate-pulse">Registrazione in corso…</span>
                        </button>
                        <div x-show="webhookResult" x-cloak class="text-sm">
                            <span x-show="webhookResult?.success" class="text-green-400">
                                ✓ <span x-text="webhookResult?.description"></span>
                            </span>
                            <span x-show="!webhookResult?.success" class="text-red-400">
                                ✗ <span x-text="webhookResult?.message"></span>
                            </span>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- STEP 6: Sicurezza                                            --}}
            {{-- ============================================================ --}}
            <div x-show="currentStep === 6" x-cloak>
                <div class="space-y-5">

                    <div class="bg-gray-800 rounded-lg p-4 text-xs text-gray-400 space-y-3">
                        <p class="text-white font-semibold">Modello di sicurezza di FlamingDragon</p>
                        <p>FlamingDragon usa un approccio di sicurezza a più livelli. Ogni livello è indipendente dagli altri — se uno fallisce, gli altri tengono.</p>
                    </div>

                    <div class="space-y-3">
                        @php
                            $secLayers = [
                                ['n' => '1', 'title' => 'Chat ID Allow-list (Telegram)', 'color' => 'red', 'desc' => 'Solo i Chat ID configurati in .env possono inviare comandi. I messaggi di chiunque altro vengono ignorati silenziosamente — nessuna risposta, nessun log.'],
                                ['n' => '2', 'title' => 'Webhook Secret Token', 'color' => 'orange', 'desc' => 'Ogni richiesta webhook deve contenere il secret token nell\'header X-Telegram-Bot-Api-Secret-Token. Richieste senza token valido restituiscono HTTP 200 vuoto (no information leakage).'],
                                ['n' => '3', 'title' => 'Command Allow-list', 'color' => 'yellow', 'desc' => 'Solo i comandi presenti nella tabella allowed_commands con is_active=true possono essere eseguiti. Nessun comando può essere inventato al volo.'],
                                ['n' => '4', 'title' => 'Tool Restrictions per sessione', 'color' => 'green', 'desc' => 'Ogni sessione agente può usare solo i tool esplicitamente configurati per quel comando. Un agente che processa "status" non può usare "bash" anche se volesse.'],
                                ['n' => '5', 'title' => 'Path Sandbox', 'color' => 'blue', 'desc' => 'Tutti gli accessi ai file sono validati contro allowed_base_paths e blocked_paths. I symlink vengono risolti prima della validazione per prevenire path traversal.'],
                                ['n' => '6', 'title' => 'Infrastruttura (Nginx/VPN)', 'color' => 'purple', 'desc' => 'La dashboard web non ha autenticazione applicativa — è protetta a livello infrastrutturale da Nginx (IP whitelist) o VPN. Configura questo a livello server.'],
                            ];
                        @endphp

                        @foreach($secLayers as $layer)
                            <div class="flex gap-4 bg-gray-800 rounded-lg p-4">
                                <div class="shrink-0 w-8 h-8 rounded-full bg-{{ $layer['color'] }}-900 border border-{{ $layer['color'] }}-700 flex items-center justify-center text-{{ $layer['color'] }}-400 text-xs font-bold">
                                    {{ $layer['n'] }}
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-white mb-1">{{ $layer['title'] }}</div>
                                    <div class="text-xs text-gray-400">{{ $layer['desc'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="bg-gray-800 rounded-lg p-4 text-xs">
                        <p class="text-white font-semibold mb-3">Configurazione Nginx consigliata per la dashboard:</p>
                        <pre class="text-green-400 font-mono whitespace-pre-wrap">location / {
    # Whitelist IP — sostituisci con il tuo
    allow 192.168.1.0/24;
    allow 1.2.3.4;          # il tuo IP fisso
    deny all;

    try_files $uri $uri/ /index.php?$query_string;
}

location /api/telegram/webhook {
    # Il webhook deve essere raggiungibile da Telegram
    allow all;
    try_files $uri $uri/ /index.php?$query_string;
}</pre>
                    </div>

                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- STEP 7: Test finale                                          --}}
            {{-- ============================================================ --}}
            <div x-show="currentStep === 7" x-cloak>
                <div class="space-y-5">

                    <div class="bg-gray-800 rounded-lg p-4 text-xs text-gray-400 space-y-2">
                        <p class="text-white font-semibold">Verifica finale del sistema</p>
                        <p>Clicca il pulsante per inviare un messaggio di test al tuo Telegram. Se lo ricevi, FlamingDragon è configurato correttamente.</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button @click="sendTestMessage()"
                                :disabled="testing"
                                class="px-5 py-3 bg-brand hover:bg-orange-600 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition flex items-center gap-2">
                            <span x-show="!testing">🚀 Invia messaggio di test a Telegram</span>
                            <span x-show="testing" class="animate-pulse">Invio in corso…</span>
                        </button>
                    </div>

                    <div x-show="testMessageResult" x-cloak class="rounded-lg p-4"
                         :class="testMessageResult?.success ? 'bg-green-950 border border-green-700' : 'bg-red-950 border border-red-700'">
                        <div class="text-sm" :class="testMessageResult?.success ? 'text-green-300' : 'text-red-300'">
                            <span x-show="testMessageResult?.success">
                                ✓ <strong>Messaggio inviato!</strong>
                                Controlla Telegram — dovresti aver ricevuto il messaggio di benvenuto.
                                Chat ID: <code x-text="testMessageResult?.chat_id"></code>
                            </span>
                            <span x-show="!testMessageResult?.success">
                                ✗ <span x-text="testMessageResult?.message"></span>
                                <br><span class="text-xs mt-1">Verifica il token, il chat ID e che il webhook sia raggiungibile.</span>
                            </span>
                        </div>
                    </div>

                    <div class="bg-gray-800 rounded-lg p-4 text-xs space-y-3">
                        <p class="text-white font-semibold">Comandi Telegram disponibili subito:</p>
                        <div class="grid grid-cols-2 gap-2 font-mono">
                            @php
                                $cmds = [
                                    ['/help', 'Lista tutti i comandi'],
                                    ['/status', 'Stato del sistema'],
                                    ['/skills', 'Skill installate'],
                                    ['/memory', 'Cerca nelle memorie'],
                                    ['/remember chiave valore', 'Salva una memoria'],
                                    ['/logs 5', 'Ultimi 5 log'],
                                    ['/providers', 'Provider LLM attivi'],
                                    ['/cancel uuid', 'Cancella una sessione'],
                                ];
                            @endphp
                            @foreach($cmds as [$cmd, $desc])
                                <div class="bg-gray-900 rounded p-2">
                                    <div class="text-brand text-xs">{{ $cmd }}</div>
                                    <div class="text-gray-500 text-xs mt-0.5">{{ $desc }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- STEP 8: Completato                                           --}}
            {{-- ============================================================ --}}
            <div x-show="currentStep === 8" x-cloak>
                <div class="text-center space-y-6 py-6">
                    <div class="text-6xl">🔥</div>
                    <h2 class="text-2xl font-bold text-white">FlamingDragon è pronto!</h2>
                    <p class="text-gray-400 text-sm max-w-md mx-auto">
                        La configurazione è completata. Il tuo agente AI personale è attivo e in ascolto su Telegram.
                    </p>

                    <div class="grid grid-cols-3 gap-4 mt-6 text-xs">
                        <a href="{{ route('dashboard') }}"
                           class="flex flex-col items-center gap-2 bg-gray-800 hover:bg-gray-700 rounded-lg p-4 transition text-gray-300 hover:text-white">
                            <span class="text-2xl">⬛</span>
                            <span>Dashboard</span>
                        </a>
                        <a href="{{ route('logs.index') }}"
                           class="flex flex-col items-center gap-2 bg-gray-800 hover:bg-gray-700 rounded-lg p-4 transition text-gray-300 hover:text-white">
                            <span class="text-2xl">📋</span>
                            <span>Logs</span>
                        </a>
                        <a href="{{ route('commands.index') }}"
                           class="flex flex-col items-center gap-2 bg-gray-800 hover:bg-gray-700 rounded-lg p-4 transition text-gray-300 hover:text-white">
                            <span class="text-2xl">⚡</span>
                            <span>Comandi</span>
                        </a>
                    </div>
                </div>
            </div>

        </div>

        {{-- ---------------------------------------------------------------- --}}
        {{-- Save status bar                                                   --}}
        {{-- ---------------------------------------------------------------- --}}
        <div x-show="saveStatus" x-cloak
             class="px-6 py-2 text-xs border-t border-gray-800"
             :class="saveStatus === 'ok' ? 'bg-green-950 text-green-400 border-green-800' : 'bg-red-950 text-red-400 border-red-800'">
            <span x-show="saveStatus === 'ok'">✓ Configurazione salvata nel file .env</span>
            <span x-show="saveStatus !== 'ok'" x-text="'✗ ' + saveError"></span>
        </div>

        {{-- ---------------------------------------------------------------- --}}
        {{-- Navigation buttons                                                --}}
        {{-- ---------------------------------------------------------------- --}}
        <div class="px-6 py-4 border-t border-gray-800 flex items-center justify-between">
            <button @click="prevStep()"
                    x-show="currentStep > 0 && currentStep < steps.length - 1"
                    class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-sm text-gray-300 rounded-lg transition">
                ← Indietro
            </button>
            <div x-show="currentStep === 0"></div>

            <div class="flex items-center gap-3">
                {{-- Salva + Avanti (per step con input) --}}
                <button x-show="needsSave() && currentStep < steps.length - 1"
                        @click="saveAndNext()"
                        :disabled="saving"
                        class="px-5 py-2 bg-brand hover:bg-orange-600 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition flex items-center gap-2">
                    <span x-show="!saving">Salva e continua →</span>
                    <span x-show="saving" class="animate-pulse">Salvataggio…</span>
                </button>

                {{-- Solo Avanti (per step senza input) --}}
                <button x-show="!needsSave() && currentStep < steps.length - 1"
                        @click="nextStep()"
                        class="px-5 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm font-semibold rounded-lg transition">
                    Continua →
                </button>

                {{-- Ultimo step (completato) --}}
                <a x-show="currentStep === steps.length - 1"
                   href="{{ route('dashboard') }}"
                   class="px-5 py-2 bg-green-700 hover:bg-green-600 text-white text-sm font-semibold rounded-lg transition">
                    Vai alla Dashboard
                </a>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function wizard() {
    return {
        currentStep: 0,
        maxReached: 0,
        testing: false,
        saving: false,
        saveStatus: null,
        saveError: '',

        telegramTestResult: null,
        llmTestResult: null,
        webhookResult: null,
        testMessageResult: null,

        form: {
            botToken:       '{{ addslashes($botToken) }}',
            webhookSecret:  '{{ addslashes($webhookSecret) }}',
            allowedChatIds: '{{ addslashes($allowedChatIds) }}',
            llmProvider:    '{{ addslashes($defaultProvider) }}',
            llmModel:       '{{ addslashes($defaultModel) }}',
            anthropicKey:   '',
            openaiKey:      '',
            ollamaUrl:      '{{ addslashes(env("FD_OLLAMA_BASE_URL", "http://localhost:11434")) }}',
            webhookUrl:     '{{ addslashes(url("/api/telegram/webhook")) }}',
        },

        llmProviders: @json($providers),

        steps: [
            { shortLabel: 'Benvenuto',   label: 'Benvenuto in FlamingDragon',     icon: '🔥', subtitle: 'Panoramica dello stato attuale del sistema' },
            { shortLabel: 'Bot Token',   label: 'Configurazione Bot Telegram',    icon: '🤖', subtitle: 'Crea il tuo bot e ottieni il token API' },
            { shortLabel: 'Chat ID',     label: 'Identità e autorizzazione',       icon: '🔑', subtitle: 'Configura chi può inviare comandi' },
            { shortLabel: 'Provider LLM',label: 'Provider AI (LLM)',              icon: '🧠', subtitle: 'Scegli e configura il modello linguistico' },
            { shortLabel: 'Queue',       label: 'Queue Worker',                   icon: '⚙️', subtitle: 'Esecuzione asincrona dei comandi' },
            { shortLabel: 'Webhook',     label: 'Registrazione Webhook Telegram', icon: '🌐', subtitle: 'Connetti FlamingDragon a Telegram' },
            { shortLabel: 'Sicurezza',   label: 'Modello di sicurezza',           icon: '🛡️', subtitle: 'Comprendi come è protetto il sistema' },
            { shortLabel: 'Test',        label: 'Test finale',                    icon: '🧪', subtitle: 'Verifica che tutto funzioni correttamente' },
            { shortLabel: 'Fatto!',      label: 'Configurazione completata',      icon: '✅', subtitle: 'FlamingDragon è pronto' },
        ],

        isProviderConfigured(provider) {
            if (provider.name === 'anthropic') return this.form.anthropicKey.length > 0 || '{{ !empty(env("ANTHROPIC_API_KEY")) }}' === '1';
            if (provider.name === 'openai')    return this.form.openaiKey.length > 0 || '{{ !empty(env("OPENAI_API_KEY")) }}' === '1';
            if (provider.name === 'ollama')    return true; // no key needed
            return false;
        },

        needsSave() {
            return [1, 2, 3].includes(this.currentStep);
        },

        goToStep(idx) {
            if (idx > this.maxReached) return;
            this.currentStep = idx;
            this.clearResults();
        },

        prevStep() {
            if (this.currentStep > 0) {
                this.currentStep--;
                this.clearResults();
            }
        },

        nextStep() {
            if (this.currentStep < this.steps.length - 1) {
                this.currentStep++;
                this.maxReached = Math.max(this.maxReached, this.currentStep);
                this.clearResults();
            }
        },

        clearResults() {
            this.telegramTestResult = null;
            this.llmTestResult = null;
            this.webhookResult = null;
            this.saveStatus = null;
            this.saveError = '';
        },

        async saveAndNext() {
            this.saving = true;
            this.saveStatus = null;

            const pairs = this.buildPairsForCurrentStep();
            if (Object.keys(pairs).length === 0) {
                this.saving = false;
                this.nextStep();
                return;
            }

            try {
                const resp = await this.post('{{ route("wizard.save-env") }}', { values: pairs });
                if (resp.success) {
                    this.saveStatus = 'ok';
                    setTimeout(() => { this.saveStatus = null; this.nextStep(); }, 800);
                } else {
                    this.saveStatus = 'error';
                    this.saveError = resp.message || 'Errore sconosciuto.';
                }
            } catch (e) {
                this.saveStatus = 'error';
                this.saveError = e.message || 'Errore di rete.';
            } finally {
                this.saving = false;
            }
        },

        buildPairsForCurrentStep() {
            if (this.currentStep === 1) {
                const p = {};
                if (this.form.botToken)      p['FD_TELEGRAM_BOT_TOKEN']    = this.form.botToken;
                if (this.form.webhookSecret) p['FD_TELEGRAM_WEBHOOK_SECRET'] = this.form.webhookSecret;
                return p;
            }
            if (this.currentStep === 2) {
                const p = {};
                if (this.form.allowedChatIds) p['FD_TELEGRAM_ALLOWED_CHAT_IDS'] = this.form.allowedChatIds;
                return p;
            }
            if (this.currentStep === 3) {
                const p = {
                    FD_LLM_DEFAULT_PROVIDER: this.form.llmProvider,
                    FD_LLM_DEFAULT_MODEL:    this.form.llmModel,
                };
                if (this.form.llmProvider === 'anthropic' && this.form.anthropicKey) p['ANTHROPIC_API_KEY'] = this.form.anthropicKey;
                if (this.form.llmProvider === 'openai'    && this.form.openaiKey)    p['OPENAI_API_KEY']    = this.form.openaiKey;
                if (this.form.llmProvider === 'ollama'    && this.form.ollamaUrl)    p['FD_OLLAMA_BASE_URL'] = this.form.ollamaUrl;
                return p;
            }
            return {};
        },

        async testTelegram() {
            if (!this.form.botToken) return;
            this.testing = true;
            this.telegramTestResult = null;
            try {
                this.telegramTestResult = await this.post('{{ route("wizard.test-telegram") }}', { token: this.form.botToken });
            } finally {
                this.testing = false;
            }
        },

        async testLlm() {
            this.testing = true;
            this.llmTestResult = null;

            // Save the key first, then test
            const pairs = this.buildPairsForCurrentStep();
            if (Object.keys(pairs).length > 0) {
                await this.post('{{ route("wizard.save-env") }}', { values: pairs });
            }

            try {
                this.llmTestResult = await this.post('{{ route("wizard.test-llm") }}', { provider: this.form.llmProvider });
            } finally {
                this.testing = false;
            }
        },

        async registerWebhook() {
            this.testing = true;
            this.webhookResult = null;
            try {
                this.webhookResult = await this.post('{{ route("wizard.register-webhook") }}', {
                    token:  this.form.botToken,
                    url:    this.form.webhookUrl,
                    secret: this.form.webhookSecret,
                });
            } finally {
                this.testing = false;
            }
        },

        async sendTestMessage() {
            this.testing = true;
            this.testMessageResult = null;
            try {
                const firstChatId = this.form.allowedChatIds
                    ? this.form.allowedChatIds.split(',').map(s => s.trim()).filter(Boolean)[0] ?? ''
                    : '';
                this.testMessageResult = await this.post('{{ route("wizard.send-test-message") }}', {
                    token:   this.form.botToken,
                    chat_id: firstChatId,
                });
            } finally {
                this.testing = false;
            }
        },

        randomSecret() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            return Array.from({ length: 32 }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
        },

        async post(url, data) {
            const resp = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify(data),
            });
            return resp.json();
        },
    };
}
</script>
@endpush
