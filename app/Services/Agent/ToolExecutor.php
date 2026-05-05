<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Models\ExecutionLog;
use App\Services\Security\SessionSandbox;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Executes tools within a session sandbox with full logging.
 */
class ToolExecutor
{
    public function __construct(
        private readonly SessionSandbox $sandbox,
    ) {}

    /**
     * Execute a named tool with the given arguments.
     *
     * @param  string                $toolName
     * @param  array<string, mixed>  $arguments
     * @param  int                   $sessionId
     * @param  int                   $stepNumber
     * @return array{output: string, success: bool}
     */
    public function execute(
        string $toolName,
        array $arguments,
        int $sessionId,
        int $stepNumber,
    ): array {
        $startTime = microtime(true);

        try {
            $this->sandbox->recordToolCall();

            $output = $this->dispatch($toolName, $arguments);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->logStep(
                sessionId:  $sessionId,
                stepNumber: $stepNumber,
                toolName:   $toolName,
                input:      json_encode($arguments),
                output:     $output,
                durationMs: $durationMs,
            );

            return ['output' => $output, 'success' => true];
        } catch (Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $errorMessage = $e->getMessage();

            $this->logStep(
                sessionId:  $sessionId,
                stepNumber: $stepNumber,
                toolName:   $toolName,
                input:      json_encode($arguments),
                output:     "ERROR: {$errorMessage}",
                durationMs: $durationMs,
                isError:    true,
            );

            Log::warning('[ToolExecutor] Tool execution failed.', [
                'tool'  => $toolName,
                'error' => $errorMessage,
            ]);

            return ['output' => "Tool '{$toolName}' failed: {$errorMessage}", 'success' => false];
        }
    }

    /**
     * Dispatch to the appropriate built-in tool handler.
     *
     * @param  string                $toolName
     * @param  array<string, mixed>  $args
     * @return string
     * @throws RuntimeException
     */
    private function dispatch(string $toolName, array $args): string
    {
        return match($toolName) {
            'bash'               => $this->runBash($args),
            'file_read'          => $this->runFileRead($args),
            'file_write'         => $this->runFileWrite($args),
            'file_list'          => $this->runFileList($args),
            'file_delete'        => $this->runFileDelete($args),
            'file_search'        => $this->runFileSearch($args),
            'http_get'           => $this->runHttpGet($args),
            'http_post'          => $this->runHttpPost($args),
            'json_api'           => $this->runJsonApi($args),
            'web_search'         => $this->runWebSearch($args),
            'weather'            => $this->runWeather($args),
            'summarize_url'      => $this->runSummarizeUrl($args),
            'send_email'         => $this->runSendEmail($args),
            'image_generate'     => $this->runImageGenerate($args),
            'db_query'           => $this->runDbQuery($args),
            'cron_list'          => $this->runCronList($args),
            'memory_read'        => $this->runMemoryRead($args),
            'memory_write'       => $this->runMemoryWrite($args),
            'telegram_send'      => $this->runTelegramSend($args),
            'skill_read'         => $this->runSkillRead($args),
            'process_status'     => $this->runProcessStatus($args),
            'laravel_artisan'    => $this->runArtisan($args),
            'git_operation'      => $this->runGit($args),
            'composer_operation'    => $this->runComposer($args),
            'npm_operation'         => $this->runNpm($args),
            // Gmail
            'gmail_list'            => $this->runGmailList($args),
            'gmail_read'            => $this->runGmailRead($args),
            'gmail_send'            => $this->runGmailSend($args),
            'gmail_search'          => $this->runGmailSearch($args),
            'gmail_mark_read'       => $this->runGmailMarkRead($args),
            'gmail_trash'           => $this->runGmailTrash($args),
            // Google Calendar
            'google_calendar_list'  => $this->runGoogleCalendarList($args),
            'google_calendar_create'=> $this->runGoogleCalendarCreate($args),
            'google_calendar_delete'=> $this->runGoogleCalendarDelete($args),
            // Todos
            'todo_create'           => $this->runTodoCreate($args),
            'todo_list'             => $this->runTodoList($args),
            'todo_complete'         => $this->runTodoComplete($args),
            'todo_delete'           => $this->runTodoDelete($args),
            // Shopping list
            'shopping_add'          => $this->runShoppingAdd($args),
            'shopping_items'        => $this->runShoppingItems($args),
            'shopping_bought'       => $this->runShoppingBought($args),
            'shopping_clear'        => $this->runShoppingClear($args),
            // Messaging
            'whatsapp_send'         => $this->runWhatsappSend($args),
            // Social media
            'facebook_post'         => $this->runFacebookPost($args),
            'facebook_feed'         => $this->runFacebookFeed($args),
            'instagram_post'        => $this->runInstagramPost($args),
            // Document generation
            'generate_qr'           => $this->runGenerateQr($args),
            'generate_pdf'          => $this->runGeneratePdf($args),
            'generate_docx'         => $this->runGenerateDocx($args),
            'generate_xlsx'         => $this->runGenerateXlsx($args),
            // Vision / image
            'analyze_image'         => $this->runAnalyzeImage($args),
            'generate_image'        => $this->runGenerateImage($args),
            'send_telegram_image'   => $this->runSendTelegramImage($args),
            // Audio
            'transcribe_audio'      => $this->runTranscribeAudio($args),
            'generate_audio'        => $this->runGenerateAudio($args),
            'send_telegram_voice'   => $this->runSendTelegramVoice($args),
            default                 => throw new RuntimeException("Unknown tool: {$toolName}"),
        };
    }

    // -------------------------------------------------------------------------
    // Tool implementations
    // -------------------------------------------------------------------------

    private function runBash(array $args): string
    {
        $command = $args['command'] ?? throw new RuntimeException('bash: missing required argument "command".');
        // Path validation not applicable for commands themselves, but outputs may reference paths
        $output = [];
        $code   = 0;
        exec($command . ' 2>&1', $output, $code);
        $result = implode("\n", $output);
        if ($code !== 0) {
            return "Exit code {$code}:\n{$result}";
        }
        return $result;
    }

    private function runFileRead(array $args): string
    {
        $path = $args['path'] ?? throw new RuntimeException('file_read: missing required argument "path".');
        $this->sandbox->validatePath($path);
        if (! file_exists($path)) {
            throw new RuntimeException("file_read: file not found at '{$path}'.");
        }
        $content = file_get_contents($path);
        return $content !== false ? $content : throw new RuntimeException("file_read: unable to read '{$path}'.");
    }

    private function runFileWrite(array $args): string
    {
        $path    = $args['path']    ?? throw new RuntimeException('file_write: missing required argument "path".');
        $content = $args['content'] ?? throw new RuntimeException('file_write: missing required argument "content".');
        $this->sandbox->validatePath(dirname($path));
        $result = file_put_contents($path, $content);
        if ($result === false) {
            throw new RuntimeException("file_write: unable to write to '{$path}'.");
        }
        return "Written {$result} bytes to '{$path}'.";
    }

    private function runFileList(array $args): string
    {
        $path = $args['path'] ?? '.';
        $this->sandbox->validatePath($path);
        if (! is_dir($path)) {
            throw new RuntimeException("file_list: '{$path}' is not a directory.");
        }
        $items = scandir($path);
        return implode("\n", array_diff($items ?: [], ['.', '..']));
    }

    private function runHttpGet(array $args): string
    {
        $url = $args['url'] ?? throw new RuntimeException('http_get: missing required argument "url".');
        $response = \Illuminate\Support\Facades\Http::timeout(30)->get($url);
        return substr($response->body(), 0, 8000);
    }

    private function runHttpPost(array $args): string
    {
        $url     = $args['url']     ?? throw new RuntimeException('http_post: missing required argument "url".');
        $payload = $args['payload'] ?? [];
        $response = \Illuminate\Support\Facades\Http::timeout(30)->post($url, $payload);
        return substr($response->body(), 0, 8000);
    }

    private function runMemoryRead(array $args): string
    {
        $key       = $args['key']       ?? null;
        $namespace = $args['namespace'] ?? 'general';

        if ($key !== null) {
            $memory = \App\Models\Memory::where('namespace', $namespace)->where('key', $key)->first();
            return $memory?->value ?? "No memory found for {$namespace}/{$key}.";
        }

        $memories = \App\Models\Memory::where('namespace', $namespace)->get();
        return $memories->map(fn ($m) => "{$m->key}: {$m->value}")->implode("\n");
    }

    private function runMemoryWrite(array $args): string
    {
        $key       = $args['key']       ?? throw new RuntimeException('memory_write: missing argument "key".');
        $value     = $args['value']     ?? throw new RuntimeException('memory_write: missing argument "value".');
        $namespace = $args['namespace'] ?? 'general';

        \App\Models\Memory::updateOrCreate(
            ['namespace' => $namespace, 'key' => $key],
            ['value' => $value, 'memory_type' => 'fact']
        );

        return "Memory stored: {$namespace}/{$key}";
    }

    private function runTelegramSend(array $args): string
    {
        $text = $args['text'] ?? throw new RuntimeException('telegram_send: missing argument "text".');
        $chatIds = config('flamingdragon.telegram.allowed_chat_ids', []);
        if (empty($chatIds)) {
            return 'telegram_send: no authorized chat IDs configured.';
        }
        $service = app(\App\Services\Telegram\TelegramService::class);
        $service->sendMessage($chatIds[0], $text);
        return 'Message sent to Telegram.';
    }

    private function runSkillRead(array $args): string
    {
        $name  = $args['name'] ?? throw new RuntimeException('skill_read: missing argument "name".');
        $skill = \App\Models\Skill::where('name', $name)->where('is_active', true)->first();
        if ($skill === null) {
            return "Skill '{$name}' not found.";
        }
        return $skill->readMarkdown() ?? "SKILL.md could not be read.";
    }

    private function runProcessStatus(array $args): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = [];
            exec('tasklist 2>&1', $output);
            return implode("\n", array_slice($output, 0, 40));
        }
        $output = [];
        exec('ps aux 2>&1', $output);
        return implode("\n", array_slice($output, 0, 40));
    }

    private function runArtisan(array $args): string
    {
        $command    = $args['command'] ?? throw new RuntimeException('laravel_artisan: missing argument "command".');
        $artisan    = base_path('artisan');
        $fullCmd    = PHP_BINARY . " {$artisan} {$command} 2>&1";
        $output     = [];
        $code       = 0;
        exec($fullCmd, $output, $code);
        return implode("\n", $output);
    }

    private function runGit(array $args): string
    {
        $op      = $args['operation'] ?? 'status';
        $path    = $args['path']      ?? base_path();

        // Restrict to safe operations
        $allowed = ['pull', 'status', 'log', 'diff', 'fetch'];
        if (! in_array($op, $allowed, true)) {
            throw new RuntimeException("git_operation: '{$op}' is not an allowed git operation.");
        }

        $output = [];
        $code   = 0;
        exec("git -C " . escapeshellarg($path) . " {$op} 2>&1", $output, $code);
        return implode("\n", $output);
    }

    private function runComposer(array $args): string
    {
        $op      = $args['operation'] ?? 'install';
        $path    = $args['path']      ?? base_path();
        $allowed = ['install', 'update', 'dump-autoload'];

        if (! in_array($op, $allowed, true)) {
            throw new RuntimeException("composer_operation: '{$op}' is not an allowed composer operation.");
        }

        $output = [];
        exec("composer {$op} --working-dir=" . escapeshellarg($path) . " 2>&1", $output);
        return implode("\n", $output);
    }

    private function runFileDelete(array $args): string
    {
        $path = $args['path'] ?? throw new RuntimeException('file_delete: missing argument "path".');
        $this->sandbox->validatePath($path);
        if (! file_exists($path)) {
            throw new RuntimeException("file_delete: file not found at '{$path}'.");
        }
        if (! unlink($path)) {
            throw new RuntimeException("file_delete: could not delete '{$path}'.");
        }
        return "Deleted '{$path}'.";
    }

    private function runFileSearch(array $args): string
    {
        $query   = $args['query']     ?? throw new RuntimeException('file_search: missing argument "query".');
        $path    = $args['path']      ?? base_path();
        $mode    = $args['mode']      ?? 'name'; // 'name' or 'content'
        $this->sandbox->validatePath($path);

        $results = [];
        $iter    = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        $count   = 0;

        foreach ($iter as $file) {
            if ($count >= 50) { $results[] = '… (truncated at 50 results)'; break; }
            if (! $file->isFile()) continue;

            if ($mode === 'name' && stripos($file->getFilename(), $query) !== false) {
                $results[] = $file->getPathname();
                $count++;
            } elseif ($mode === 'content') {
                $content = @file_get_contents($file->getPathname());
                if ($content && stripos($content, $query) !== false) {
                    $results[] = $file->getPathname();
                    $count++;
                }
            }
        }

        return empty($results) ? 'No files found.' : implode("\n", $results);
    }

    private function runJsonApi(array $args): string
    {
        $url     = $args['url']     ?? throw new RuntimeException('json_api: missing argument "url".');
        $method  = strtoupper($args['method']  ?? 'GET');
        $payload = $args['payload'] ?? [];
        $headers = $args['headers'] ?? [];

        $http = \Illuminate\Support\Facades\Http::timeout(30)->withHeaders($headers);

        $response = match($method) {
            'POST'  => $http->post($url, $payload),
            'PUT'   => $http->put($url, $payload),
            'PATCH' => $http->patch($url, $payload),
            default => $http->get($url, $payload),
        };

        $body = $response->json();
        return json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: $response->body();
    }

    private function runWebSearch(array $args): string
    {
        $query = $args['query'] ?? throw new RuntimeException('web_search: missing argument "query".');
        $limit = min((int)($args['limit'] ?? 5), 10);

        // DuckDuckGo Instant Answer API (free, no key)
        $response = \Illuminate\Support\Facades\Http::timeout(10)
            ->withHeaders(['User-Agent' => 'FlamingDragon/1.0'])
            ->get('https://api.duckduckgo.com/', [
                'q'      => $query,
                'format' => 'json',
                'no_html'=> '1',
                'no_redirect' => '1',
            ]);

        $data    = $response->json();
        $results = [];

        if (! empty($data['AbstractText'])) {
            $results[] = "📖 " . $data['AbstractText'];
            if (! empty($data['AbstractURL'])) {
                $results[] = "   Source: " . $data['AbstractURL'];
            }
        }

        foreach (array_slice($data['RelatedTopics'] ?? [], 0, $limit) as $topic) {
            if (isset($topic['Text'])) {
                $results[] = "• " . $topic['Text'];
                if (isset($topic['FirstURL'])) {
                    $results[] = "  " . $topic['FirstURL'];
                }
            }
        }

        return empty($results)
            ? "No results found for: {$query}"
            : implode("\n", $results);
    }

    private function runWeather(array $args): string
    {
        $location = $args['location'] ?? throw new RuntimeException('weather: missing argument "location".');

        // Geocode via Open-Meteo geocoding (free)
        $geo = \Illuminate\Support\Facades\Http::timeout(10)
            ->get('https://geocoding-api.open-meteo.com/v1/search', [
                'name'  => $location,
                'count' => 1,
            ])->json();

        $place = $geo['results'][0] ?? null;
        if (! $place) {
            return "Location '{$location}' not found.";
        }

        $lat  = $place['latitude'];
        $lon  = $place['longitude'];
        $name = $place['name'] . ', ' . ($place['country'] ?? '');

        $weather = \Illuminate\Support\Facades\Http::timeout(10)
            ->get('https://api.open-meteo.com/v1/forecast', [
                'latitude'       => $lat,
                'longitude'      => $lon,
                'current_weather'=> 'true',
                'hourly'         => 'relativehumidity_2m',
                'forecast_days'  => 1,
            ])->json();

        $cw   = $weather['current_weather'] ?? [];
        $temp = $cw['temperature']  ?? '?';
        $wind = $cw['windspeed']    ?? '?';
        $code = $cw['weathercode']  ?? 0;

        $descriptions = [
            0=>'Clear sky',1=>'Mainly clear',2=>'Partly cloudy',3=>'Overcast',
            45=>'Foggy',48=>'Icy fog',51=>'Light drizzle',53=>'Drizzle',55=>'Heavy drizzle',
            61=>'Light rain',63=>'Rain',65=>'Heavy rain',71=>'Light snow',73=>'Snow',75=>'Heavy snow',
            80=>'Rain showers',81=>'Rain showers',82=>'Heavy rain showers',
            95=>'Thunderstorm',96=>'Thunderstorm with hail',99=>'Thunderstorm with heavy hail',
        ];
        $desc = $descriptions[$code] ?? "Code {$code}";

        return "📍 {$name}\n🌡 {$temp}°C · {$desc}\n💨 Wind: {$wind} km/h";
    }

    private function runSummarizeUrl(array $args): string
    {
        $url = $args['url'] ?? throw new RuntimeException('summarize_url: missing argument "url".');

        $html = \Illuminate\Support\Facades\Http::timeout(15)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 FlamingDragon/1.0'])
            ->get($url)
            ->body();

        // Strip tags, collapse whitespace
        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim(mb_substr($text, 0, 6000));

        if (empty($text)) {
            return "Could not extract text from {$url}.";
        }

        $llm      = app(\App\Services\Llm\LlmRouter::class);
        $response = $llm->chat([
            ['role' => 'user', 'content' => "Summarize the following web page content in 3-5 bullet points:\n\n{$text}"],
        ]);

        return "Summary of {$url}:\n" . $response->content;
    }

    private function runSendEmail(array $args): string
    {
        $to      = $args['to']      ?? throw new RuntimeException('send_email: missing argument "to".');
        $subject = $args['subject'] ?? throw new RuntimeException('send_email: missing argument "subject".');
        $body    = $args['body']    ?? throw new RuntimeException('send_email: missing argument "body".');

        try {
            \Illuminate\Support\Facades\Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
            return "Email sent to {$to}.";
        } catch (\Throwable $e) {
            throw new RuntimeException("send_email failed: " . $e->getMessage());
        }
    }

    private function runImageGenerate(array $args): string
    {
        $prompt = $args['prompt'] ?? throw new RuntimeException('image_generate: missing argument "prompt".');
        $size   = $args['size']   ?? '1024x1024';
        $apiKey = env('OPENAI_API_KEY', '');

        if (empty($apiKey)) {
            throw new RuntimeException('image_generate: OPENAI_API_KEY not configured.');
        }

        $response = \Illuminate\Support\Facades\Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/images/generations', [
                'model'  => 'dall-e-3',
                'prompt' => $prompt,
                'n'      => 1,
                'size'   => $size,
            ]);

        $url = $response->json('data.0.url');
        if (! $url) {
            throw new RuntimeException('image_generate: ' . ($response->json('error.message') ?? 'Unknown error'));
        }

        return "Generated image: {$url}";
    }

    private function runDbQuery(array $args): string
    {
        $sql = $args['sql'] ?? throw new RuntimeException('db_query: missing argument "sql".');

        // Only allow SELECT
        $trimmed = ltrim($sql);
        if (! preg_match('/^SELECT\s/i', $trimmed)) {
            throw new RuntimeException('db_query: only SELECT queries are allowed.');
        }

        try {
            $results = \Illuminate\Support\Facades\DB::select($sql);
            if (empty($results)) return 'Query returned 0 rows.';
            $rows = array_map(fn($r) => json_encode((array)$r), array_slice($results, 0, 50));
            $out  = implode("\n", $rows);
            if (count($results) > 50) $out .= "\n… (truncated at 50 rows)";
            return $out;
        } catch (\Throwable $e) {
            throw new RuntimeException('db_query error: ' . $e->getMessage());
        }
    }

    private function runCronList(array $args): string
    {
        $output = [];
        \Illuminate\Support\Facades\Artisan::call('schedule:list', [], $outputBuffer = new \Symfony\Component\Console\Output\BufferedOutput());
        return $outputBuffer->fetch() ?: 'No scheduled tasks found.';
    }

    private function runNpm(array $args): string
    {
        $op      = $args['operation'] ?? 'install';
        $path    = $args['path']      ?? base_path();
        $allowed = ['install', 'run', 'build', 'ci'];

        if (! in_array($op, $allowed, true)) {
            throw new RuntimeException("npm_operation: '{$op}' is not an allowed npm operation.");
        }

        $script  = $args['script'] ?? '';
        $cmd     = "npm {$op}" . ($script ? " {$script}" : '') . " --prefix " . escapeshellarg($path) . " 2>&1";
        $output  = [];
        exec($cmd, $output);
        return implode("\n", $output);
    }

    // =========================================================================
    // Gmail
    // =========================================================================

    /**
     * Decode a base64url-encoded string (Gmail uses URL-safe base64).
     */
    private function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Encode a string as base64url (URL-safe base64, no padding).
     */
    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Recursively extract the plain-text body from a Gmail message payload.
     */
    private function extractGmailBody(array $payload): string
    {
        $mimeType = $payload['mimeType'] ?? '';

        // Direct text/plain
        if ($mimeType === 'text/plain' && isset($payload['body']['data'])) {
            return $this->base64urlDecode($payload['body']['data']);
        }

        // Multipart — recurse into parts
        if (str_starts_with($mimeType, 'multipart/') && isset($payload['parts'])) {
            foreach ($payload['parts'] as $part) {
                $text = $this->extractGmailBody($part);
                if ($text !== '') return $text;
            }
        }

        // Fallback: try text/html and strip tags
        if ($mimeType === 'text/html' && isset($payload['body']['data'])) {
            $html = $this->base64urlDecode($payload['body']['data']);
            return trim(strip_tags($html));
        }

        return '';
    }

    /**
     * Get a header value from a Gmail message headers array.
     */
    private function gmailHeader(array $headers, string $name): string
    {
        foreach ($headers as $h) {
            if (strcasecmp($h['name'], $name) === 0) {
                return $h['value'] ?? '';
            }
        }
        return '';
    }

    private function runGmailList(array $args): string
    {
        $token      = $this->gmailAccessToken();
        $maxResults = min((int)($args['max_results'] ?? 10), 50);
        $query      = $args['query']  ?? 'in:inbox';
        $label      = $args['label']  ?? '';

        $params = ['maxResults' => $maxResults, 'q' => $query];
        if ($label) $params['labelIds'] = $label;

        $list = \Illuminate\Support\Facades\Http::withToken($token)
            ->get('https://gmail.googleapis.com/gmail/v1/users/me/messages', $params)
            ->json();

        $messages = $list['messages'] ?? [];
        if (empty($messages)) {
            return 'No messages found.';
        }

        $lines = [];
        foreach ($messages as $msg) {
            $detail  = \Illuminate\Support\Facades\Http::withToken($token)
                ->get("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$msg['id']}", [
                    'format' => 'metadata',
                    'metadataHeaders' => 'Subject,From,Date',
                ])->json();

            $headers = $detail['payload']['headers'] ?? [];
            $subject = $this->gmailHeader($headers, 'Subject') ?: '(no subject)';
            $from    = $this->gmailHeader($headers, 'From');
            $date    = $this->gmailHeader($headers, 'Date');
            $snippet = mb_substr($detail['snippet'] ?? '', 0, 100);
            $unread  = in_array('UNREAD', $detail['labelIds'] ?? []) ? ' 🔵' : '';

            $lines[] = "[{$msg['id']}]{$unread}\n  From: {$from}\n  Date: {$date}\n  Subject: {$subject}\n  Preview: {$snippet}";
        }

        return "Messages ({$maxResults} max):\n\n" . implode("\n\n", $lines);
    }

    private function runGmailRead(array $args): string
    {
        $messageId = $args['message_id'] ?? throw new RuntimeException('gmail_read: missing "message_id"');
        $token     = $this->gmailAccessToken();

        $message = \Illuminate\Support\Facades\Http::withToken($token)
            ->get("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}", ['format' => 'full'])
            ->json();

        if (isset($message['error'])) {
            throw new RuntimeException('gmail_read: ' . ($message['error']['message'] ?? 'Unknown error'));
        }

        $headers = $message['payload']['headers'] ?? [];
        $subject = $this->gmailHeader($headers, 'Subject');
        $from    = $this->gmailHeader($headers, 'From');
        $to      = $this->gmailHeader($headers, 'To');
        $date    = $this->gmailHeader($headers, 'Date');
        $body    = $this->extractGmailBody($message['payload'] ?? []);

        // Mark as read (remove UNREAD label)
        if (in_array('UNREAD', $message['labelIds'] ?? [])) {
            \Illuminate\Support\Facades\Http::withToken($token)
                ->post("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}/modify", [
                    'removeLabelIds' => ['UNREAD'],
                ]);
        }

        $body = mb_substr($body, 0, 4000);
        return "From: {$from}\nTo: {$to}\nDate: {$date}\nSubject: {$subject}\n\n{$body}";
    }

    private function runGmailSend(array $args): string
    {
        $to      = $args['to']      ?? throw new RuntimeException('gmail_send: missing "to"');
        $subject = $args['subject'] ?? throw new RuntimeException('gmail_send: missing "subject"');
        $body    = $args['body']    ?? throw new RuntimeException('gmail_send: missing "body"');
        $cc      = $args['cc']      ?? '';
        $bcc     = $args['bcc']     ?? '';
        $token   = $this->gmailAccessToken();

        // Get sender's email address
        $profile = \Illuminate\Support\Facades\Http::withToken($token)
            ->get('https://gmail.googleapis.com/gmail/v1/users/me/profile')
            ->json();
        $from = $profile['emailAddress'] ?? 'me';

        // Build RFC 2822 message
        $headers  = "From: {$from}\r\nTo: {$to}\r\n";
        if ($cc)  $headers .= "Cc: {$cc}\r\n";
        if ($bcc) $headers .= "Bcc: {$bcc}\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";

        $rawMessage = $headers . "\r\n" . base64_encode($body);
        $encoded    = $this->base64urlEncode($rawMessage);

        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => $encoded,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('gmail_send: ' . ($response->json('error.message') ?? $response->body()));
        }

        $msgId = $response->json('id') ?? '?';
        return "Email sent to {$to} [id: {$msgId}]\nSubject: {$subject}";
    }

    private function runGmailSearch(array $args): string
    {
        $query      = $args['query']       ?? throw new RuntimeException('gmail_search: missing "query"');
        $maxResults = min((int)($args['max_results'] ?? 10), 50);
        $token      = $this->gmailAccessToken();

        $list = \Illuminate\Support\Facades\Http::withToken($token)
            ->get('https://gmail.googleapis.com/gmail/v1/users/me/messages', [
                'q'          => $query,
                'maxResults' => $maxResults,
            ])->json();

        $messages = $list['messages'] ?? [];
        if (empty($messages)) {
            return "No messages matching: {$query}";
        }

        $lines = [];
        foreach ($messages as $msg) {
            $detail  = \Illuminate\Support\Facades\Http::withToken($token)
                ->get("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$msg['id']}", [
                    'format' => 'metadata',
                    'metadataHeaders' => 'Subject,From,Date',
                ])->json();

            $headers = $detail['payload']['headers'] ?? [];
            $subject = $this->gmailHeader($headers, 'Subject') ?: '(no subject)';
            $from    = $this->gmailHeader($headers, 'From');
            $date    = $this->gmailHeader($headers, 'Date');

            $lines[] = "[{$msg['id']}] {$subject}\n  From: {$from} — {$date}";
        }

        return "Search results for \"{$query}\":\n\n" . implode("\n\n", $lines);
    }

    private function runGmailMarkRead(array $args): string
    {
        $messageId = $args['message_id'] ?? throw new RuntimeException('gmail_mark_read: missing "message_id"');
        $token     = $this->gmailAccessToken();

        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->post("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}/modify", [
                'removeLabelIds' => ['UNREAD'],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('gmail_mark_read: ' . ($response->json('error.message') ?? $response->body()));
        }

        return "Message [{$messageId}] marked as read.";
    }

    private function runGmailTrash(array $args): string
    {
        $messageId = $args['message_id'] ?? throw new RuntimeException('gmail_trash: missing "message_id"');
        $token     = $this->gmailAccessToken();

        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->post("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}/trash");

        if (! $response->successful()) {
            throw new RuntimeException('gmail_trash: ' . ($response->json('error.message') ?? $response->body()));
        }

        return "Message [{$messageId}] moved to trash.";
    }

    // =========================================================================
    // Google OAuth2 shared helper
    // =========================================================================

    /**
     * Exchange a Google OAuth2 refresh token for a short-lived access token.
     * GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET must be set in .env.
     */
    private function googleOAuthToken(string $refreshToken, string $context = 'Google'): string
    {
        $clientId     = env('GOOGLE_CLIENT_ID');
        $clientSecret = env('GOOGLE_CLIENT_SECRET');

        if (! $clientId || ! $clientSecret) {
            throw new RuntimeException("{$context}: GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET must be set in .env");
        }

        $response = \Illuminate\Support\Facades\Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
        ]);

        $token = $response->json('access_token');
        if (! $token) {
            throw new RuntimeException("{$context}: failed to obtain access token — " . $response->body());
        }

        return $token;
    }

    /** Access token for Google Calendar (scope: calendar). */
    private function googleAccessToken(): string
    {
        $rt = env('GOOGLE_REFRESH_TOKEN')
            ?? throw new RuntimeException('Google Calendar: GOOGLE_REFRESH_TOKEN must be set in .env');
        return $this->googleOAuthToken($rt, 'Google Calendar');
    }

    /** Access token for Gmail (scope: gmail.modify). Falls back to GOOGLE_REFRESH_TOKEN if not set separately. */
    private function gmailAccessToken(): string
    {
        $rt = env('GOOGLE_GMAIL_REFRESH_TOKEN') ?: env('GOOGLE_REFRESH_TOKEN');
        if (! $rt) {
            throw new RuntimeException('Gmail: GOOGLE_GMAIL_REFRESH_TOKEN (or GOOGLE_REFRESH_TOKEN) must be set in .env with Gmail scope');
        }
        return $this->googleOAuthToken($rt, 'Gmail');
    }

    // =========================================================================
    // Google Calendar
    // =========================================================================

    private function runGoogleCalendarList(array $args): string
    {
        $calendarId = env('GOOGLE_CALENDAR_ID', 'primary');
        $daysAhead  = max(1, min((int)($args['days_ahead'] ?? 7), 90));
        $maxResults = min((int)($args['max_results'] ?? 10), 50);
        $token      = $this->googleAccessToken();

        $timeMin = (new \DateTime())->format(\DateTime::RFC3339);
        $timeMax = (new \DateTime("+{$daysAhead} days"))->format(\DateTime::RFC3339);

        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->get("https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendarId) . "/events", [
                'timeMin'      => $timeMin,
                'timeMax'      => $timeMax,
                'maxResults'   => $maxResults,
                'singleEvents' => 'true',
                'orderBy'      => 'startTime',
            ]);

        $items = $response->json('items') ?? [];
        if (empty($items)) {
            return "No events in the next {$daysAhead} days.";
        }

        $lines = [];
        foreach ($items as $event) {
            $start = $event['start']['dateTime'] ?? $event['start']['date'] ?? '?';
            $end   = $event['end']['dateTime']   ?? $event['end']['date']   ?? '?';
            $id    = $event['id'] ?? '?';
            $title = $event['summary'] ?? '(no title)';
            $loc   = isset($event['location']) ? " @ {$event['location']}" : '';
            $lines[] = "[{$id}] {$title}{$loc}\n  Start: {$start} → End: {$end}";
        }

        return "Upcoming events:\n\n" . implode("\n\n", $lines);
    }

    private function runGoogleCalendarCreate(array $args): string
    {
        $calendarId = env('GOOGLE_CALENDAR_ID', 'primary');
        $token      = $this->googleAccessToken();
        $timezone   = $args['timezone']    ?? 'Europe/Rome';
        $title      = $args['title']       ?? throw new RuntimeException('google_calendar_create: missing "title"');
        $start      = $args['start']       ?? throw new RuntimeException('google_calendar_create: missing "start" (ISO 8601)');
        $end        = $args['end']         ?? throw new RuntimeException('google_calendar_create: missing "end" (ISO 8601)');

        $body = [
            'summary'  => $title,
            'start'    => ['dateTime' => $start, 'timeZone' => $timezone],
            'end'      => ['dateTime' => $end,   'timeZone' => $timezone],
        ];
        if (isset($args['description'])) $body['description'] = $args['description'];
        if (isset($args['location']))    $body['location']    = $args['location'];

        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->post("https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendarId) . "/events", $body);

        if (! $response->successful()) {
            throw new RuntimeException('google_calendar_create: ' . ($response->json('error.message') ?? $response->body()));
        }

        $id = $response->json('id');
        return "Event created: '{$title}' [{$id}]\n  From: {$start}\n  To:   {$end}";
    }

    private function runGoogleCalendarDelete(array $args): string
    {
        $calendarId = env('GOOGLE_CALENDAR_ID', 'primary');
        $eventId    = $args['event_id'] ?? throw new RuntimeException('google_calendar_delete: missing "event_id"');
        $token      = $this->googleAccessToken();

        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->delete("https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendarId) . "/events/" . urlencode($eventId));

        if ($response->status() === 204 || $response->successful()) {
            return "Event [{$eventId}] deleted.";
        }

        throw new RuntimeException('google_calendar_delete: ' . ($response->json('error.message') ?? $response->body()));
    }

    // =========================================================================
    // Todos
    // =========================================================================

    private function runTodoCreate(array $args): string
    {
        $title    = $args['title']     ?? throw new RuntimeException('todo_create: missing "title"');
        $listName = $args['list_name'] ?? 'default';
        $priority = in_array($args['priority'] ?? 'normal', ['low', 'normal', 'high']) ? $args['priority'] : 'normal';
        $dueAt    = $args['due_at']    ?? null;
        $notes    = $args['notes']     ?? null;

        $id = \Illuminate\Support\Facades\DB::table('fd_todos')->insertGetId([
            'title'      => $title,
            'list_name'  => $listName,
            'priority'   => $priority,
            'notes'      => $notes,
            'due_at'     => $dueAt,
            'is_done'    => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return "Todo #{$id} created: '{$title}' in list '{$listName}'" . ($dueAt ? " (due: {$dueAt})" : '');
    }

    private function runTodoList(array $args): string
    {
        $listName  = $args['list_name']  ?? 'default';
        $showDone  = filter_var($args['show_done'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = \Illuminate\Support\Facades\DB::table('fd_todos');

        if ($listName !== 'all') {
            $query->where('list_name', $listName);
        }

        if (! $showDone) {
            $query->where('is_done', false);
        }

        $todos = $query->orderByRaw("FIELD(priority,'high','normal','low')")->orderBy('due_at')->get();

        if ($todos->isEmpty()) {
            $label = $listName === 'all' ? 'any list' : "list '{$listName}'";
            return "No" . ($showDone ? '' : ' pending') . " todos in {$label}.";
        }

        $lines = $todos->map(function ($t) {
            $done   = $t->is_done ? '✓' : '○';
            $due    = $t->due_at  ? " [due: {$t->due_at}]" : '';
            $pri    = $t->priority !== 'normal' ? " [{$t->priority}]" : '';
            return "#{$t->id} {$done} {$t->title}{$pri}{$due}" . ($t->list_name !== 'default' ? " ({$t->list_name})" : '');
        })->implode("\n");

        return $lines;
    }

    private function runTodoComplete(array $args): string
    {
        $id = (int)($args['id'] ?? throw new RuntimeException('todo_complete: missing "id"'));
        $rows = \Illuminate\Support\Facades\DB::table('fd_todos')
            ->where('id', $id)
            ->update(['is_done' => true, 'done_at' => now(), 'updated_at' => now()]);

        return $rows ? "Todo #{$id} marked as done." : "Todo #{$id} not found.";
    }

    private function runTodoDelete(array $args): string
    {
        $id   = (int)($args['id'] ?? throw new RuntimeException('todo_delete: missing "id"'));
        $rows = \Illuminate\Support\Facades\DB::table('fd_todos')->where('id', $id)->delete();
        return $rows ? "Todo #{$id} deleted." : "Todo #{$id} not found.";
    }

    // =========================================================================
    // Shopping list
    // =========================================================================

    private function runShoppingAdd(array $args): string
    {
        $name     = $args['name']      ?? throw new RuntimeException('shopping_add: missing "name"');
        $listName = $args['list_name'] ?? 'default';
        $category = $args['category']  ?? null;
        $quantity = (float)($args['quantity'] ?? 1);
        $unit     = $args['unit']      ?? null;

        $id = \Illuminate\Support\Facades\DB::table('fd_shopping_items')->insertGetId([
            'name'       => $name,
            'list_name'  => $listName,
            'category'   => $category,
            'quantity'   => $quantity,
            'unit'       => $unit,
            'is_bought'  => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $qty = ($quantity != 1 || $unit) ? " {$quantity}" . ($unit ? " {$unit}" : '') : '';
        return "Added#{$id}: {$name}{$qty}" . ($category ? " [{$category}]" : '') . " → list '{$listName}'";
    }

    private function runShoppingItems(array $args): string
    {
        $listName  = $args['list_name']  ?? 'default';
        $showBought = filter_var($args['show_bought'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = \Illuminate\Support\Facades\DB::table('fd_shopping_items')->where('list_name', $listName);
        if (! $showBought) {
            $query->where('is_bought', false);
        }

        $items = $query->orderBy('category')->orderBy('name')->get();

        if ($items->isEmpty()) {
            return "Shopping list '{$listName}' is " . ($showBought ? 'empty' : 'empty or all items bought') . '.';
        }

        $grouped = $items->groupBy('category');
        $lines   = [];

        foreach ($grouped as $cat => $catItems) {
            if ($cat) $lines[] = "\n{$cat}:";
            foreach ($catItems as $item) {
                $bought = $item->is_bought ? ' ✓' : '';
                $qty    = ($item->quantity != 1 || $item->unit) ? " {$item->quantity}" . ($item->unit ? " {$item->unit}" : '') : '';
                $lines[] = "  #{$item->id} {$item->name}{$qty}{$bought}";
            }
        }

        return "Shopping list '{$listName}':" . implode("\n", $lines);
    }

    private function runShoppingBought(array $args): string
    {
        $id   = (int)($args['id'] ?? throw new RuntimeException('shopping_bought: missing "id"'));
        $rows = \Illuminate\Support\Facades\DB::table('fd_shopping_items')
            ->where('id', $id)
            ->update(['is_bought' => true, 'bought_at' => now(), 'updated_at' => now()]);

        return $rows ? "Item #{$id} marked as bought." : "Item #{$id} not found.";
    }

    private function runShoppingClear(array $args): string
    {
        $listName = $args['list_name'] ?? 'default';
        $deleted  = \Illuminate\Support\Facades\DB::table('fd_shopping_items')
            ->where('list_name', $listName)
            ->where('is_bought', true)
            ->delete();

        return "Cleared {$deleted} bought item(s) from list '{$listName}'.";
    }

    // =========================================================================
    // WhatsApp
    // =========================================================================

    private function runWhatsappSend(array $args): string
    {
        $to       = $args['to']      ?? throw new RuntimeException('whatsapp_send: missing "to" (phone number)');
        $message  = $args['message'] ?? throw new RuntimeException('whatsapp_send: missing "message"');
        $phoneId  = env('WHATSAPP_PHONE_NUMBER_ID');
        $token    = env('WHATSAPP_ACCESS_TOKEN');

        if (! $phoneId || ! $token) {
            throw new RuntimeException('whatsapp_send: WHATSAPP_PHONE_NUMBER_ID and WHATSAPP_ACCESS_TOKEN must be set in .env');
        }

        // Clean phone number
        $to = preg_replace('/\D/', '', $to);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $message],
        ];

        if (isset($args['template'])) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'template',
                'template'          => ['name' => $args['template'], 'language' => ['code' => 'en_US']],
            ];
        }

        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->timeout(15)
            ->post("https://graph.facebook.com/v19.0/{$phoneId}/messages", $payload);

        if (! $response->successful()) {
            throw new RuntimeException('whatsapp_send: ' . ($response->json('error.message') ?? $response->body()));
        }

        $msgId = $response->json('messages.0.id') ?? '?';
        return "WhatsApp message sent to +{$to} [id: {$msgId}]";
    }

    // =========================================================================
    // Social media
    // =========================================================================

    private function runFacebookPost(array $args): string
    {
        $message = $args['message'] ?? throw new RuntimeException('facebook_post: missing "message"');
        $pageId  = env('FACEBOOK_PAGE_ID');
        $token   = env('FACEBOOK_PAGE_ACCESS_TOKEN');

        if (! $pageId || ! $token) {
            throw new RuntimeException('facebook_post: FACEBOOK_PAGE_ID and FACEBOOK_PAGE_ACCESS_TOKEN must be set in .env');
        }

        $payload = ['message' => $message, 'access_token' => $token];
        if (isset($args['link']))      $payload['link']      = $args['link'];
        if (isset($args['image_url'])) $payload['picture']   = $args['image_url'];

        $response = \Illuminate\Support\Facades\Http::timeout(15)
            ->post("https://graph.facebook.com/v19.0/{$pageId}/feed", $payload);

        if (! $response->successful()) {
            throw new RuntimeException('facebook_post: ' . ($response->json('error.message') ?? $response->body()));
        }

        $postId = $response->json('id') ?? '?';
        return "Posted to Facebook page {$pageId} [post id: {$postId}]";
    }

    private function runFacebookFeed(array $args): string
    {
        $pageId = env('FACEBOOK_PAGE_ID');
        $token  = env('FACEBOOK_PAGE_ACCESS_TOKEN');
        $limit  = min((int)($args['limit'] ?? 5), 20);

        if (! $pageId || ! $token) {
            throw new RuntimeException('facebook_feed: FACEBOOK_PAGE_ID and FACEBOOK_PAGE_ACCESS_TOKEN must be set in .env');
        }

        $response = \Illuminate\Support\Facades\Http::timeout(15)
            ->get("https://graph.facebook.com/v19.0/{$pageId}/feed", [
                'access_token' => $token,
                'fields'       => 'id,message,story,created_time',
                'limit'        => $limit,
            ]);

        $data = $response->json('data') ?? [];
        if (empty($data)) {
            return 'No posts found.';
        }

        $lines = array_map(fn($p) =>
            "[{$p['id']}] {$p['created_time']}\n  " . mb_substr($p['message'] ?? $p['story'] ?? '(no text)', 0, 200),
            $data
        );

        return implode("\n\n", $lines);
    }

    private function runInstagramPost(array $args): string
    {
        $imageUrl  = $args['image_url'] ?? throw new RuntimeException('instagram_post: missing "image_url"');
        $caption   = $args['caption']   ?? '';
        $igAccountId = env('INSTAGRAM_BUSINESS_ACCOUNT_ID');
        $token       = env('FACEBOOK_PAGE_ACCESS_TOKEN');

        if (! $igAccountId || ! $token) {
            throw new RuntimeException('instagram_post: INSTAGRAM_BUSINESS_ACCOUNT_ID and FACEBOOK_PAGE_ACCESS_TOKEN must be set in .env');
        }

        // Step 1: Create media container
        $container = \Illuminate\Support\Facades\Http::timeout(30)
            ->post("https://graph.facebook.com/v19.0/{$igAccountId}/media", [
                'image_url'    => $imageUrl,
                'caption'      => $caption,
                'access_token' => $token,
            ]);

        if (! $container->successful()) {
            throw new RuntimeException('instagram_post (media): ' . ($container->json('error.message') ?? $container->body()));
        }

        $containerId = $container->json('id');

        // Step 2: Publish
        $publish = \Illuminate\Support\Facades\Http::timeout(30)
            ->post("https://graph.facebook.com/v19.0/{$igAccountId}/media_publish", [
                'creation_id'  => $containerId,
                'access_token' => $token,
            ]);

        if (! $publish->successful()) {
            throw new RuntimeException('instagram_post (publish): ' . ($publish->json('error.message') ?? $publish->body()));
        }

        $mediaId = $publish->json('id') ?? '?';
        return "Posted to Instagram [media id: {$mediaId}]";
    }

    // =========================================================================
    // Document generation
    // =========================================================================

    private function runGenerateQr(array $args): string
    {
        $content = $args['content'] ?? throw new RuntimeException('generate_qr: missing "content"');
        $size    = min((int)($args['size'] ?? 300), 1000);
        $size    = max($size, 50);

        // Use qrserver.com free API — returns image directly
        $url = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'data'   => $content,
            'size'   => "{$size}x{$size}",
            'format' => 'png',
        ]);

        return "QR code generated:\n{$url}\n\nContent: {$content}";
    }

    private function ensureGeneratedDir(): string
    {
        $dir = storage_path('app/public/generated');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private function runGeneratePdf(array $args): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $args['filename'] ?? 'document');
        $html     = $args['html']    ?? throw new RuntimeException('generate_pdf: missing "html"');
        $title    = $args['title']   ?? $filename;

        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class) && ! class_exists(\Dompdf\Dompdf::class)) {
            throw new RuntimeException('generate_pdf: package not installed. Run: composer require barryvdh/laravel-dompdf');
        }

        $dir  = $this->ensureGeneratedDir();
        $path = "{$dir}/{$filename}.pdf";

        $fullHtml = "<!DOCTYPE html><html><head><meta charset='utf-8'><title>{$title}</title>"
            . "<style>body{font-family:sans-serif;padding:20px;}</style></head><body>{$html}</body></html>";

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($fullHtml);
            file_put_contents($path, $pdf->output());
        } else {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($fullHtml);
            $dompdf->setPaper('A4');
            $dompdf->render();
            file_put_contents($path, $dompdf->output());
        }

        $publicUrl = url("storage/generated/{$filename}.pdf");
        return "PDF generated: {$filename}.pdf\nDownload: {$publicUrl}";
    }

    private function runGenerateDocx(array $args): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $args['filename'] ?? 'document');
        $title    = $args['title']   ?? $filename;
        $content  = $args['content'] ?? [];

        if (! class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
            throw new RuntimeException('generate_docx: package not installed. Run: composer require phpoffice/phpword');
        }

        $dir  = $this->ensureGeneratedDir();
        $path = "{$dir}/{$filename}.docx";

        $phpWord  = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->getDefaultFontName('Calibri');
        $phpWord->getDefaultFontSize(11);

        $section = $phpWord->addSection();
        $section->addTitle($title, 1);

        foreach ($content as $block) {
            $type = $block['type'] ?? 'paragraph';
            $text = $block['text'] ?? '';

            if ($type === 'heading') {
                $section->addTitle($text, 2);
            } elseif ($type === 'table' && isset($block['data'])) {
                $table = $section->addTable();
                foreach ($block['data'] as $row) {
                    $tr = $table->addRow();
                    foreach ((array) $row as $cell) {
                        $tr->addCell(2000)->addText((string) $cell);
                    }
                }
            } else {
                $section->addText($text);
            }
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($path);

        $publicUrl = url("storage/generated/{$filename}.docx");
        return "Word document generated: {$filename}.docx\nDownload: {$publicUrl}";
    }

    private function runGenerateXlsx(array $args): string
    {
        $filename  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $args['filename'] ?? 'spreadsheet');
        $sheetName = $args['sheet_name'] ?? 'Sheet1';
        $headers   = $args['headers']    ?? [];
        $rows      = $args['rows']       ?? [];

        if (! class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            throw new RuntimeException('generate_xlsx: package not installed. Run: composer require phpoffice/phpspreadsheet');
        }

        $dir  = $this->ensureGeneratedDir();
        $path = "{$dir}/{$filename}.xlsx";

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);

        // Write headers
        if (! empty($headers)) {
            foreach ($headers as $col => $header) {
                $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
                $sheet->getColumnDimensionByColumn($col + 1)->setAutoSize(true);
            }
            // Bold header row
            $sheet->getStyle('1:1')->getFont()->setBold(true);
        }

        // Write data rows
        $startRow = empty($headers) ? 1 : 2;
        foreach ($rows as $rowIdx => $row) {
            foreach ((array) $row as $col => $value) {
                $sheet->setCellValueByColumnAndRow($col + 1, $startRow + $rowIdx, $value);
            }
        }

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($path);

        $publicUrl = url("storage/generated/{$filename}.xlsx");
        return "Excel spreadsheet generated: {$filename}.xlsx\nDownload: {$publicUrl}";
    }

    // =========================================================================
    // Vision / Image tools
    // =========================================================================

    /**
     * Analyze an image using a vision-capable model (GPT-4o or Claude claude-opus-4-6).
     * $args: image_path (local absolute path OR public URL), question (optional)
     */
    private function runAnalyzeImage(array $args): string
    {
        $imagePath = $args['image_path'] ?? $args['url'] ?? throw new RuntimeException('analyze_image: missing "image_path" or "url".');
        $question  = $args['question'] ?? 'Describe this image in detail.';

        // Determine image source
        $isUrl = filter_var($imagePath, FILTER_VALIDATE_URL) !== false;

        if ($isUrl) {
            $imageContent = [
                'type'      => 'image_url',
                'image_url' => ['url' => $imagePath, 'detail' => 'high'],
            ];
        } else {
            if (! file_exists($imagePath)) {
                throw new RuntimeException("analyze_image: file not found at '{$imagePath}'.");
            }
            $ext      = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            $mimeMap  = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
            $mime     = $mimeMap[$ext] ?? 'image/jpeg';
            $b64      = base64_encode(file_get_contents($imagePath));
            $imageContent = [
                'type'      => 'image_url',
                'image_url' => ['url' => "data:{$mime};base64,{$b64}", 'detail' => 'high'],
            ];
        }

        // Use OpenAI GPT-4o for vision (it reliably supports image_url content blocks)
        $apiKey = env('OPENAI_API_KEY', '');
        if (empty($apiKey)) {
            throw new RuntimeException('analyze_image: OPENAI_API_KEY is not configured.');
        }

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model'    => 'gpt-4o',
            'messages' => [
                [
                    'role'    => 'user',
                    'content' => [
                        $imageContent,
                        ['type' => 'text', 'text' => $question],
                    ],
                ],
            ],
            'max_tokens' => 1024,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('analyze_image: OpenAI API error: ' . $response->body());
        }

        return $response->json('choices.0.message.content') ?? 'No description returned.';
    }

    /**
     * Generate an image with DALL-E 3 and return the public URL.
     * $args: prompt, size (optional: 1024x1024, 1792x1024, 1024x1792), quality (optional: standard/hd)
     */
    private function runGenerateImage(array $args): string
    {
        $prompt  = $args['prompt'] ?? throw new RuntimeException('generate_image: missing "prompt".');
        $size    = $args['size']    ?? '1024x1024';
        $quality = $args['quality'] ?? 'standard';

        $apiKey = env('OPENAI_API_KEY', '');
        if (empty($apiKey)) {
            throw new RuntimeException('generate_image: OPENAI_API_KEY is not configured.');
        }

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/images/generations', [
            'model'   => 'dall-e-3',
            'prompt'  => $prompt,
            'n'       => 1,
            'size'    => $size,
            'quality' => $quality,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('generate_image: DALL-E API error: ' . $response->body());
        }

        $imageUrl = $response->json('data.0.url');
        if (! $imageUrl) {
            throw new RuntimeException('generate_image: no image URL in response.');
        }

        return $imageUrl;
    }

    /**
     * Send a generated or existing image to the Telegram user.
     * $args: image (URL or local path), caption (optional)
     */
    private function runSendTelegramImage(array $args): string
    {
        $image   = $args['image']   ?? $args['url'] ?? $args['path'] ?? throw new RuntimeException('send_telegram_image: missing "image".');
        $caption = $args['caption'] ?? '';

        $chatId = config('flamingdragon.telegram.allowed_chat_ids');
        if (is_array($chatId)) {
            $chatId = $chatId[0] ?? null;
        }
        if (! $chatId) {
            throw new RuntimeException('send_telegram_image: no allowed_chat_ids configured.');
        }

        $telegram = app(\App\Services\Telegram\TelegramService::class);
        $ok = $telegram->sendPhoto((int) $chatId, $image, $caption);

        return $ok ? 'Image sent successfully.' : 'Failed to send image.';
    }

    // =========================================================================
    // Audio tools
    // =========================================================================

    /**
     * Transcribe an audio file using OpenAI Whisper.
     * $args: audio_path (local absolute path), language (optional ISO-639-1, e.g. "it")
     */
    private function runTranscribeAudio(array $args): string
    {
        $audioPath = $args['audio_path'] ?? $args['path'] ?? throw new RuntimeException('transcribe_audio: missing "audio_path".');
        $language  = $args['language']   ?? null;

        if (! file_exists($audioPath)) {
            throw new RuntimeException("transcribe_audio: file not found at '{$audioPath}'.");
        }

        $apiKey = env('OPENAI_API_KEY', '');
        if (empty($apiKey)) {
            throw new RuntimeException('transcribe_audio: OPENAI_API_KEY is not configured.');
        }

        $request = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])->timeout(120)->attach(
            'file', file_get_contents($audioPath), basename($audioPath)
        );

        $payload = ['model' => 'whisper-1', 'response_format' => 'text'];
        if ($language) {
            $payload['language'] = $language;
        }

        $response = $request->post('https://api.openai.com/v1/audio/transcriptions', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('transcribe_audio: Whisper API error: ' . $response->body());
        }

        // When response_format=text the body is plain text
        return trim($response->body());
    }

    /**
     * Generate speech audio from text using OpenAI TTS, save locally, return local path.
     * $args: text, voice (optional: alloy/echo/fable/onyx/nova/shimmer), speed (optional 0.25-4.0)
     */
    private function runGenerateAudio(array $args): string
    {
        $text  = $args['text']  ?? throw new RuntimeException('generate_audio: missing "text".');
        $voice = $args['voice'] ?? 'nova';
        $speed = (float) ($args['speed'] ?? 1.0);

        $apiKey = env('OPENAI_API_KEY', '');
        if (empty($apiKey)) {
            throw new RuntimeException('generate_audio: OPENAI_API_KEY is not configured.');
        }

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/audio/speech', [
            'model'          => 'tts-1',
            'input'          => $text,
            'voice'          => $voice,
            'speed'          => $speed,
            'response_format'=> 'mp3',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('generate_audio: TTS API error: ' . $response->body());
        }

        $dir = storage_path('app/public/media');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename  = 'tts_' . time() . '_' . substr(md5($text), 0, 8) . '.mp3';
        $localPath = "{$dir}/{$filename}";
        file_put_contents($localPath, $response->body());

        return $localPath;
    }

    /**
     * Send an audio file as a Telegram voice message to the user.
     * $args: audio_path (local path to .ogg or .mp3), caption (optional)
     */
    private function runSendTelegramVoice(array $args): string
    {
        $audioPath = $args['audio_path'] ?? $args['path'] ?? throw new RuntimeException('send_telegram_voice: missing "audio_path".');
        $caption   = $args['caption']    ?? '';

        if (! file_exists($audioPath)) {
            throw new RuntimeException("send_telegram_voice: file not found at '{$audioPath}'.");
        }

        $chatId = config('flamingdragon.telegram.allowed_chat_ids');
        if (is_array($chatId)) {
            $chatId = $chatId[0] ?? null;
        }
        if (! $chatId) {
            throw new RuntimeException('send_telegram_voice: no allowed_chat_ids configured.');
        }

        $telegram = app(\App\Services\Telegram\TelegramService::class);

        // Use sendAudio for MP3 files (playable inline), sendVoice for OGG
        $ext = strtolower(pathinfo($audioPath, PATHINFO_EXTENSION));
        if ($ext === 'ogg') {
            $ok = $telegram->sendVoice((int) $chatId, $audioPath, $caption);
        } else {
            $ok = $telegram->sendAudio((int) $chatId, $audioPath, '', $caption);
        }

        return $ok ? 'Audio sent successfully.' : 'Failed to send audio.';
    }

    // =========================================================================

    private function logStep(
        int $sessionId,
        int $stepNumber,
        string $toolName,
        ?string $input,
        ?string $output,
        int $durationMs,
        bool $isError = false,
    ): void {
        try {
            ExecutionLog::create([
                'session_id'  => $sessionId,
                'step_number' => $stepNumber,
                'action_type' => $isError ? 'error' : 'tool_use',
                'tool_name'   => $toolName,
                'input_data'  => $input,
                'output_data' => $output,
                'tokens_used' => 0,
                'duration_ms' => $durationMs,
                'created_at'  => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('[ToolExecutor] Failed to log step.', ['error' => $e->getMessage()]);
        }
    }
}
