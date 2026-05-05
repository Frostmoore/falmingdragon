<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║              🔥  FlamingDragon  Installer  v2               ║
 * ║  Upload to any folder on your server. Open in browser.      ║
 * ║  ⚠  DELETE this file after installation!                    ║
 * ╚══════════════════════════════════════════════════════════════╝
 */
declare(strict_types=1);
session_start();

// ══════════════════════════════════════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════════════════════════════════════

function fd_run(string $cmd, string $cwd = '/'): array
{
    if (! function_exists('proc_open')) {
        return ['code' => -1, 'out' => '', 'err' => 'proc_open disabled'];
    }
    $desc = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $proc = proc_open($cmd, $desc, $pipes, $cwd);
    if (! is_resource($proc)) {
        return ['code' => -1, 'out' => '', 'err' => "Could not start: $cmd"];
    }
    fclose($pipes[0]);
    $out  = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err  = stream_get_contents($pipes[2]); fclose($pipes[2]);
    return ['code' => proc_close($proc), 'out' => trim((string)$out), 'err' => trim((string)$err)];
}

function fd_port_open(string $host, int $port, float $timeout = 0.5): bool
{
    $s = @fsockopen($host, $port, $e, $m, $timeout);
    if ($s) { fclose($s); return true; }
    return false;
}

function fd_find_free_port(int $from = 8080, int $to = 9200): ?int
{
    for ($p = $from; $p <= $to; $p++) {
        if (! fd_port_open('127.0.0.1', $p, 0.15)) return $p;
    }
    return null;
}

function fd_detect_db(): array
{
    $candidates = [
        ['127.0.0.1', 3306], ['localhost', 3306],
        ['db', 3306], ['mysql', 3306], ['mariadb', 3306],
        ['127.0.0.1', 3307], ['localhost', 3307],
    ];
    foreach ($candidates as [$h, $p]) {
        if (fd_port_open($h, $p, 0.5)) {
            return ['host' => $h, 'port' => (string)$p, 'found' => true];
        }
    }
    return ['host' => '127.0.0.1', 'port' => '3306', 'found' => false];
}

function fd_detect_php(): string
{
    $bins = ['php', 'php8.3', 'php8.2', 'php83', 'php82',
             '/usr/bin/php8.3', '/usr/bin/php8.2', '/usr/bin/php',
             '/usr/local/bin/php'];
    foreach ($bins as $b) {
        $r = fd_run("$b -r 'echo PHP_MAJOR_VERSION;' 2>/dev/null");
        if ($r['code'] === 0 && (int)$r['out'] >= 8) return $b;
    }
    return 'php';
}

function fd_detect_webserver(): string
{
    $sw = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
    foreach (['apache' => 'apache', 'nginx' => 'nginx', 'litespeed' => 'litespeed'] as $k => $v) {
        if (str_contains($sw, $k)) return $v;
    }
    return 'unknown';
}

function fd_detect_web_root(): string
{
    $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? __FILE__);
    if ($script) return dirname($script);
    foreach (['/var/www/html','/var/www/public_html','/srv/www/htdocs','/opt/lampp/htdocs','/usr/share/nginx/html'] as $p) {
        if (is_dir($p) && is_writable($p)) return $p;
    }
    return '/var/www/html';
}

function fd_detect_project(): ?string
{
    $dir = dirname(__FILE__);
    if (file_exists("$dir/artisan") && file_exists("$dir/composer.json")) return realpath($dir);
    return null;
}

function fd_requirements(): array
{
    $list = [];
    $list[] = ['label'=>'PHP >= 8.2',   'ok'=>version_compare(PHP_VERSION,'8.2.0','>='), 'value'=>PHP_VERSION];
    foreach (['pdo_mysql','mbstring','xml','ctype','fileinfo','bcmath','curl','openssl','intl'] as $e) {
        $ok = extension_loaded($e);
        $list[] = ['label'=>"ext-$e", 'ok'=>$ok, 'value'=>$ok?'loaded':'MISSING'];
    }
    $ok = function_exists('proc_open');
    $list[] = ['label'=>'proc_open()', 'ok'=>$ok, 'value'=>$ok?'enabled':'DISABLED'];
    $git = fd_run('git --version');
    $list[] = ['label'=>'git', 'ok'=>$git['code']===0, 'value'=>$git['code']===0?$git['out']:'not found'];
    $comp = fd_run('composer --version --no-ansi 2>/dev/null || php composer.phar --version 2>/dev/null');
    $list[] = ['label'=>'Composer', 'ok'=>$comp['code']===0, 'value'=>$comp['code']===0?explode("\n",$comp['out'])[0]:'not found'];
    return $list;
}

function fd_all_ok(array $reqs): bool
{
    foreach ($reqs as $r) { if (!$r['ok']) return false; }
    return true;
}

function fd_composer_bin(): string
{
    foreach (['composer','/usr/local/bin/composer','/usr/bin/composer'] as $b) {
        $r = fd_run("$b --version --no-ansi 2>/dev/null");
        if ($r['code'] === 0) return $b;
    }
    return 'composer';
}

function fd_write_env(string $path, array $pairs): void
{
    $lines   = file_exists($path) ? array_map(fn($l)=>rtrim($l,"\r"), file($path, FILE_IGNORE_NEW_LINES) ?: []) : [];
    $written = array_fill_keys(array_keys($pairs), false);
    $result  = [];
    foreach ($lines as $line) {
        $replaced = false;
        foreach ($pairs as $key => $value) {
            if (str_starts_with($line, "$key=")) {
                $result[] = "$key=" . fd_esc($value);
                $written[$key] = true; $replaced = true; break;
            }
        }
        if (!$replaced) $result[] = $line;
    }
    foreach ($written as $key => $found) {
        if (!$found) $result[] = "$key=" . fd_esc($pairs[$key]);
    }
    file_put_contents($path, implode("\n", $result)."\n", LOCK_EX);
}

function fd_esc(string $v): string
{
    if ($v === '') return '';
    if (preg_match('/[\s"\'#$\\\\]/', $v)) return '"'.str_replace(['"','\\'], ['\\"','\\\\'], $v).'"';
    return $v;
}

function fd_random(int $n = 32): string
{
    $c = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    return implode('', array_map(fn()=>$c[random_int(0, strlen($c)-1)], range(1,$n)));
}

function fd_redirect(string $u): never { header("Location: $u"); exit; }

// ── Web server config generators ─────────────────────────────────────────────

function fd_apache_vhost_domain(string $domain, string $root): string
{
    return "<VirtualHost *:80>
    ServerName {$domain}
    DocumentRoot {$root}/public

    <Directory {$root}/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/flamingdragon_error.log
    CustomLog \${APACHE_LOG_DIR}/flamingdragon_access.log combined
</VirtualHost>";
}

function fd_apache_vhost_port(int $port, string $root): string
{
    return "Listen {$port}

<VirtualHost *:{$port}>
    DocumentRoot {$root}/public

    <Directory {$root}/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>";
}

function fd_nginx_domain(string $domain, string $root): string
{
    $sock = glob('/var/run/php/php8*-fpm.sock')[0] ?? '/var/run/php/php8.3-fpm.sock';
    return "server {
    listen 80;
    server_name {$domain};
    root {$root}/public;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php\$ {
        fastcgi_pass unix:{$sock};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\\. { deny all; }
}";
}

function fd_nginx_port(int $port, string $root): string
{
    $sock = glob('/var/run/php/php8*-fpm.sock')[0] ?? '/var/run/php/php8.3-fpm.sock';
    return "server {
    listen {$port};
    root {$root}/public;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php\$ {
        fastcgi_pass unix:{$sock};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\\. { deny all; }
}";
}

function fd_systemd_unit(string $phpBin, string $root, int $port): string
{
    return "[Unit]
Description=FlamingDragon PHP Server
After=network.target

[Service]
Type=simple
WorkingDirectory={$root}
ExecStart={$phpBin} artisan serve --host=0.0.0.0 --port={$port}
Restart=always
RestartSec=3
Environment=APP_ENV=production

[Install]
WantedBy=multi-user.target";
}

// ══════════════════════════════════════════════════════════════════════════════
// STATE MACHINE
// ══════════════════════════════════════════════════════════════════════════════

$errors  = [];
$step    = $_SESSION['fd_step']  ?? 'requirements';
$data    = $_SESSION['fd_data']  ?? [];
$project = fd_detect_project();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── requirements ─────────────────────────────────────────────────────────
    if ($action === 'req_next') {
        if (fd_all_ok(fd_requirements())) {
            $_SESSION['fd_data']['project_root'] = $project;
            $_SESSION['fd_step'] = $project ? 'dashboard' : 'source';
            fd_redirect($_SERVER['PHP_SELF']);
        }
        $errors[] = 'Fix the failing requirements first.';
    }

    // ── source ───────────────────────────────────────────────────────────────
    if ($action === 'source_next') {
        $url    = trim($_POST['git_url']      ?? '');
        $branch = trim($_POST['branch']       ?? 'main') ?: 'main';
        $path   = rtrim(trim($_POST['install_path'] ?? ''), '/');
        if (empty($url))  $errors[] = 'Git repository URL required.';
        if (empty($path)) $errors[] = 'Install path required.';
        if (empty($errors)) {
            $_SESSION['fd_data'] = array_merge($data, [
                'git_url' => $url, 'branch' => $branch, 'project_root' => $path,
            ]);
            $_SESSION['fd_step'] = 'dashboard';
            fd_redirect($_SERVER['PHP_SELF']);
        }
    }

    // ── dashboard access ─────────────────────────────────────────────────────
    if ($action === 'dashboard_next') {
        $mode = $_POST['access_mode'] ?? 'port';
        $da   = ['mode' => $mode];

        if ($mode === 'domain') {
            $domain = trim($_POST['domain'] ?? '');
            if (empty($domain)) $errors[] = 'Domain name required.';
            $da['domain']   = $domain;
            $da['app_url']  = 'http://' . $domain;
        } elseif ($mode === 'port') {
            $port = (int)($_POST['port'] ?? fd_find_free_port() ?? 8080);
            $da['port']    = $port;
            $da['app_url'] = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ':' . $port;
        } else { // subdir
            $sub = trim($_POST['subdir'] ?? 'flamingdragon');
            $da['subdir']  = $sub;
            $da['app_url'] = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . ltrim($sub, '/');
        }

        $da['webserver'] = $_POST['webserver'] ?? fd_detect_webserver();
        $da['php_bin']   = trim($_POST['php_bin'] ?? fd_detect_php());

        if (empty($errors)) {
            $_SESSION['fd_data']['dashboard'] = $da;
            $_SESSION['fd_step'] = 'database';
            fd_redirect($_SERVER['PHP_SELF']);
        }
    }

    // ── database ─────────────────────────────────────────────────────────────
    if ($action === 'db_next') {
        $db = [
            'host'     => trim($_POST['db_host']     ?? '127.0.0.1') ?: '127.0.0.1',
            'port'     => trim($_POST['db_port']     ?? '3306')      ?: '3306',
            'database' => trim($_POST['db_database'] ?? ''),
            'username' => trim($_POST['db_username'] ?? ''),
            'password' => trim($_POST['db_password'] ?? ''),
        ];
        if (empty($db['database'])) $errors[] = 'Database name required.';
        if (empty($db['username'])) $errors[] = 'Database username required.';
        if (empty($errors)) {
            try {
                new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4",
                    $db['username'], $db['password'], [PDO::ATTR_TIMEOUT => 5]);
            } catch (\PDOException $e) {
                $errors[] = 'Connection failed: ' . $e->getMessage();
            }
        }
        if (empty($errors)) {
            $_SESSION['fd_data']['db'] = $db;
            $_SESSION['fd_step'] = 'telegram';
            fd_redirect($_SERVER['PHP_SELF']);
        }
    }

    // ── telegram ─────────────────────────────────────────────────────────────
    if ($action === 'tg_next') {
        $tg = [
            'token'    => trim($_POST['tg_token']    ?? ''),
            'secret'   => trim($_POST['tg_secret']   ?? '') ?: fd_random(),
            'chat_ids' => trim($_POST['tg_chat_ids'] ?? ''),
        ];
        if (empty($tg['token'])) $errors[] = 'Bot token required.';
        if (empty($errors)) {
            $_SESSION['fd_data']['telegram'] = $tg;
            $_SESSION['fd_step'] = 'llm';
            fd_redirect($_SERVER['PHP_SELF']);
        }
    }

    // ── llm ──────────────────────────────────────────────────────────────────
    if ($action === 'llm_next') {
        $llm = [
            'provider'      => trim($_POST['llm_provider']  ?? 'anthropic'),
            'model'         => trim($_POST['llm_model']     ?? ''),
            'anthropic_key' => trim($_POST['anthropic_key'] ?? ''),
            'openai_key'    => trim($_POST['openai_key']    ?? ''),
            'ollama_url'    => trim($_POST['ollama_url']    ?? 'http://localhost:11434'),
        ];
        if (empty($llm['model'])) $errors[] = 'Model required.';
        if ($llm['provider'] === 'anthropic' && empty($llm['anthropic_key'])) $errors[] = 'Anthropic API key required.';
        if ($llm['provider'] === 'openai'    && empty($llm['openai_key']))    $errors[] = 'OpenAI API key required.';
        if (empty($errors)) {
            $_SESSION['fd_data']['llm'] = $llm;
            $_SESSION['fd_step'] = 'install';
            fd_redirect($_SERVER['PHP_SELF']);
        }
    }

    // ── install ───────────────────────────────────────────────────────────────
    if ($action === 'run_install') {
        $d           = $_SESSION['fd_data'] ?? [];
        $root        = $d['project_root']   ?? '';
        $da          = $d['dashboard']      ?? [];
        $db          = $d['db']             ?? [];
        $tg          = $d['telegram']       ?? [];
        $llm         = $d['llm']            ?? [];
        $phpBin      = $da['php_bin']       ?? 'php';
        $appUrl      = $da['app_url']       ?? 'http://localhost';
        $log         = [];
        $ok          = true;

        $L = function(string $title, array $result) use (&$log, &$ok) {
            if ($result['code'] !== 0) $ok = false;
            $log[] = ['title'=>$title, 'ok'=>$result['code']===0,
                      'out'=>trim($result['out'].($result['err']?"\n".$result['err']:''))];
        };

        // 1. Clone
        if (! file_exists("$root/artisan")) {
            $url    = $d['git_url'] ?? '';
            $branch = $d['branch']  ?? 'main';
            $parent = dirname($root);
            $folder = basename($root);
            if (!is_dir($parent)) @mkdir($parent, 0755, true);
            $L('Git clone', fd_run("git clone --branch ".escapeshellarg($branch)." ".escapeshellarg($url)." ".escapeshellarg($folder), $parent));
        } else {
            $log[] = ['title'=>'Git clone','ok'=>true,'out'=>'Skipped — project already present'];
        }

        // 2. Composer
        if ($ok) $L('Composer install', fd_run(fd_composer_bin().' install --no-dev --optimize-autoloader --no-interaction 2>&1', $root));

        // 3. Create .env
        if ($ok) {
            $envPath = "$root/.env";
            if (!file_exists($envPath)) {
                $src = "$root/.env.example";
                file_exists($src) ? copy($src, $envPath) : file_put_contents($envPath, '');
                $log[] = ['title'=>'Create .env','ok'=>true,'out'=>'Created'];
            } else {
                $log[] = ['title'=>'Create .env','ok'=>true,'out'=>'Already exists, updating'];
            }

            $pairs = [
                'APP_ENV'   => 'production',
                'APP_DEBUG' => 'false',
                'APP_URL'   => $appUrl,
                'DB_CONNECTION' => 'mariadb',
                'DB_HOST'       => $db['host']     ?? '127.0.0.1',
                'DB_PORT'       => $db['port']      ?? '3306',
                'DB_DATABASE'   => $db['database']  ?? '',
                'DB_USERNAME'   => $db['username']  ?? '',
                'DB_PASSWORD'   => $db['password']  ?? '',
                'FD_TELEGRAM_BOT_TOKEN'        => $tg['token']    ?? '',
                'FD_TELEGRAM_WEBHOOK_SECRET'   => $tg['secret']   ?? '',
                'FD_TELEGRAM_ALLOWED_CHAT_IDS' => $tg['chat_ids'] ?? '',
                'FD_LLM_DEFAULT_PROVIDER'      => $llm['provider'] ?? 'anthropic',
                'FD_LLM_DEFAULT_MODEL'         => $llm['model']    ?? '',
                'QUEUE_CONNECTION' => 'database',
                'SESSION_DRIVER'   => 'database',
                'CACHE_STORE'      => 'database',
            ];
            if (!empty($llm['anthropic_key'])) $pairs['ANTHROPIC_API_KEY'] = $llm['anthropic_key'];
            if (!empty($llm['openai_key']))    $pairs['OPENAI_API_KEY']    = $llm['openai_key'];
            if (!empty($llm['ollama_url']))    $pairs['FD_OLLAMA_BASE_URL'] = $llm['ollama_url'];

            try { fd_write_env($envPath, $pairs); $log[] = ['title'=>'Write .env','ok'=>true,'out'=>count($pairs).' values written']; }
            catch (\Throwable $e) { $log[] = ['title'=>'Write .env','ok'=>false,'out'=>$e->getMessage()]; $ok=false; }
        }

        // 4. APP_KEY
        if ($ok) $L('Generate APP_KEY', fd_run("$phpBin artisan key:generate --force --no-interaction", $root));

        // 5. Migrate
        if ($ok) $L('Migrate database', fd_run("$phpBin artisan migrate --force --no-interaction", $root));

        // 6. Seed
        if ($ok) {
            $L('Seed: Providers', fd_run("$phpBin artisan db:seed --class=DefaultProvidersSeeder --force --no-interaction", $root));
            $L('Seed: Tools',     fd_run("$phpBin artisan db:seed --class=DefaultToolsSeeder --force --no-interaction", $root));
            $L('Seed: Commands',  fd_run("$phpBin artisan db:seed --class=DefaultCommandsSeeder --force --no-interaction", $root));
        }

        // 7. Cache + permissions
        if ($ok) {
            fd_run("$phpBin artisan config:cache", $root);
            fd_run("$phpBin artisan route:cache",  $root);
            fd_run("chmod -R 775 storage bootstrap/cache", $root);
            fd_run("chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true", $root);
            $log[] = ['title'=>'Cache & permissions','ok'=>true,'out'=>'Done'];
        }

        // 8. Subdirectory symlink
        if ($ok && ($da['mode'] ?? '') === 'subdir') {
            $webRoot = fd_detect_web_root();
            $sub     = $da['subdir'] ?? 'flamingdragon';
            $link    = "$webRoot/$sub";
            if (! file_exists($link)) {
                $res = fd_run("ln -sfn ".escapeshellarg("$root/public")." ".escapeshellarg($link));
                $log[] = ['title'=>'Symlink public/', 'ok'=>$res['code']===0,
                          'out'=>$res['code']===0?"$link → $root/public":$res['err']];
            } else {
                $log[] = ['title'=>'Symlink public/','ok'=>true,'out'=>"$link already exists"];
            }
        }

        // 9. Generate web server config
        $ws      = $da['webserver'] ?? fd_detect_webserver();
        $mode    = $da['mode'] ?? 'port';
        $wsConf  = '';
        $wsFile  = '';
        $wsInstalled = false;

        if ($mode === 'domain') {
            $domain = $da['domain'] ?? '';
            if ($ws === 'apache') {
                $wsConf = fd_apache_vhost_domain($domain, $root);
                $wsFile = "/etc/apache2/sites-available/flamingdragon.conf";
            } elseif ($ws === 'nginx') {
                $wsConf = fd_nginx_domain($domain, $root);
                $wsFile = "/etc/nginx/sites-available/flamingdragon";
            }
        } elseif ($mode === 'port') {
            $port = (int)($da['port'] ?? 8080);
            if ($ws === 'apache') {
                $wsConf = fd_apache_vhost_port($port, $root);
                $wsFile = "/etc/apache2/sites-available/flamingdragon.conf";
            } elseif ($ws === 'nginx') {
                $wsConf = fd_nginx_port($port, $root);
                $wsFile = "/etc/nginx/sites-available/flamingdragon";
            }
        }

        if ($wsConf !== '' && $wsFile !== '' && is_writable(dirname($wsFile))) {
            file_put_contents($wsFile, $wsConf);
            if ($ws === 'apache') {
                fd_run("a2ensite flamingdragon.conf 2>/dev/null && service apache2 reload 2>/dev/null || true");
            } elseif ($ws === 'nginx') {
                fd_run("ln -sfn $wsFile /etc/nginx/sites-enabled/flamingdragon 2>/dev/null || true");
                fd_run("nginx -t 2>/dev/null && service nginx reload 2>/dev/null || true");
            }
            $wsInstalled = true;
            $log[] = ['title'=>'Web server config','ok'=>true,'out'=>"Installed to $wsFile and reloaded"];
        } elseif ($wsConf !== '') {
            $log[] = ['title'=>'Web server config','ok'=>true,'out'=>"Config generated (manual install needed — shown below)"];
        }

        // 10. Systemd unit (port mode without web server config)
        $systemdUnit = '';
        if ($mode === 'port' && ($ws === 'unknown' || $wsConf === '')) {
            $port = (int)($da['port'] ?? 8080);
            $systemdUnit = fd_systemd_unit($phpBin, $root, $port);
        }

        $_SESSION['fd_log']         = $log;
        $_SESSION['fd_ok']          = $ok;
        $_SESSION['fd_ws_conf']     = $wsConf;
        $_SESSION['fd_ws_file']     = $wsFile;
        $_SESSION['fd_ws_installed']= $wsInstalled;
        $_SESSION['fd_systemd']     = $systemdUnit;
        $_SESSION['fd_step']        = 'done';
        fd_redirect($_SERVER['PHP_SELF']);
    }

    // ── back / reset ──────────────────────────────────────────────────────────
    if ($action === 'back') {
        $prev = [
            'source'    => 'requirements',
            'dashboard' => $project ? 'requirements' : 'source',
            'database'  => 'dashboard',
            'telegram'  => 'database',
            'llm'       => 'telegram',
            'install'   => 'llm',
        ];
        $_SESSION['fd_step'] = $prev[$_SESSION['fd_step'] ?? 'requirements'] ?? 'requirements';
        fd_redirect($_SERVER['PHP_SELF']);
    }
    if ($action === 'reset') { session_destroy(); fd_redirect($_SERVER['PHP_SELF']); }
}

// Reload after POST
$step = $_SESSION['fd_step'] ?? 'requirements';
$data = $_SESSION['fd_data'] ?? [];

// Pre-detect for form defaults
$dbDef  = fd_detect_db();
$freePort = fd_find_free_port();
$phpBinDef = fd_detect_php();
$wsDefault = fd_detect_webserver();
$webRootDef = fd_detect_web_root();
$currentHost = $_SERVER['HTTP_HOST'] ?? 'your-server.com';

$allSteps = $project
    ? ['requirements','dashboard','database','telegram','llm','install','done']
    : ['requirements','source','dashboard','database','telegram','llm','install','done'];

$stepLabels = [
    'requirements' => 'Requirements',
    'source'       => 'Source',
    'dashboard'    => 'Dashboard',
    'database'     => 'Database',
    'telegram'     => 'Telegram',
    'llm'          => 'LLM',
    'install'      => 'Install',
    'done'         => 'Done',
];

const MODELS_INS = [
    'anthropic' => ['claude-haiku-4-5-20251001'=>'claude-haiku-4-5 ⚡','claude-sonnet-4-6'=>'claude-sonnet-4-6','claude-opus-4-6'=>'claude-opus-4-6','claude-3-5-sonnet-20241022'=>'claude-3-5-sonnet','claude-3-5-haiku-20241022'=>'claude-3-5-haiku'],
    'openai'    => ['gpt-4o-mini'=>'gpt-4o-mini ⚡','gpt-4o'=>'gpt-4o','gpt-4-turbo'=>'gpt-4-turbo','gpt-4'=>'gpt-4','gpt-3.5-turbo'=>'gpt-3.5-turbo'],
    'ollama'    => [],
];

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>🔥 FlamingDragon Installer</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#080c10;color:#e2e8f0;font-family:'Courier New',monospace;font-size:13px;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:36px 16px 60px}
a{color:#f97316}
.card{background:#0f172a;border:1px solid #1e293b;border-radius:14px;width:100%;max-width:720px;overflow:hidden}
.hdr{background:linear-gradient(135deg,#431407 0%,#1c1917 100%);padding:22px 28px;border-bottom:1px solid #1e293b}
.hdr h1{font-size:19px;font-weight:bold;color:#f97316;letter-spacing:-.5px}
.hdr p{color:#64748b;font-size:11px;margin-top:3px}
.prog{display:flex;gap:3px;padding:14px 28px;background:#0a0f1a;border-bottom:1px solid #1e293b;flex-wrap:wrap}
.ps{font-size:10px;padding:3px 10px;border-radius:99px;color:#64748b;background:#1e293b}
.ps.active{background:#431407;color:#f97316;font-weight:bold}
.ps.done{background:#14532d;color:#86efac}
.body{padding:26px 28px}
h2{font-size:14px;font-weight:bold;color:#f1f5f9;margin-bottom:5px}
.sub{color:#64748b;font-size:11px;margin-bottom:18px}
.field{margin-bottom:14px}
label{display:block;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
input,select,textarea{width:100%;background:#1e293b;border:1px solid #334155;border-radius:7px;padding:9px 13px;color:#f1f5f9;font-family:inherit;font-size:12px;outline:none;transition:border .15s}
input:focus,select:focus,textarea:focus{border-color:#f97316}
select option{background:#1e293b}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.row3{display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px}
.hint{font-size:10px;color:#475569;margin-top:4px}
.hint code{color:#94a3b8}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:7px;font-family:inherit;font-size:12px;font-weight:700;cursor:pointer;border:none;transition:all .15s}
.btn-p{background:#f97316;color:#fff}.btn-p:hover{background:#ea580c}
.btn-s{background:#1e293b;color:#94a3b8;border:1px solid #334155}.btn-s:hover{background:#334155;color:#e2e8f0}
.btn-d{background:#7f1d1d;color:#fca5a5;border:1px solid #991b1b}.btn-d:hover{background:#991b1b}
.actions{display:flex;justify-content:space-between;align-items:center;margin-top:22px;padding-top:18px;border-top:1px solid #1e293b}
.req{display:flex;align-items:center;justify-content:space-between;padding:7px 11px;border-radius:6px;margin-bottom:3px;font-size:11px}
.req-ok{background:#052e16;border:1px solid #14532d}.req-fail{background:#450a0a;border:1px solid #7f1d1d}
.lbl-ok{color:#86efac}.lbl-fail{color:#fca5a5}
.rval{color:#475569;font-size:10px}
.errs{background:#450a0a;border:1px solid #7f1d1d;border-radius:7px;padding:11px 15px;margin-bottom:18px}
.errs li{color:#fca5a5;font-size:11px;list-style:none;padding:2px 0}.errs li::before{content:"✗ "}
.info{background:#0c1a2e;border:1px solid #1e3a5f;border-radius:7px;padding:13px 15px;margin-bottom:18px;font-size:11px;color:#93c5fd;line-height:1.7}
.info strong{color:#bfdbfe}
.info code{color:#7dd3fc;background:#0a1525;padding:1px 5px;border-radius:3px}
.radio-group{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}
.radio-card{border:1px solid #334155;border-radius:8px;padding:14px;cursor:pointer;transition:all .15s}
.radio-card:hover{border-color:#475569}
.radio-card.selected{border-color:#f97316;background:#431407/30}
.radio-card input{display:none}
.radio-card h3{font-size:12px;font-weight:bold;color:#e2e8f0;margin-bottom:4px}
.radio-card p{font-size:10px;color:#64748b;line-height:1.5}
.tag{display:inline-block;font-size:9px;padding:1px 7px;border-radius:99px;background:#1e293b;color:#64748b;border:1px solid #334155;margin-left:6px}
.tag-auto{background:#14532d22;color:#86efac;border-color:#14532d}
.li{border-radius:6px;margin-bottom:5px;overflow:hidden}
.li-title{display:flex;align-items:center;gap:7px;padding:7px 11px;font-size:11px;font-weight:600}
.li-ok .li-title{background:#052e16;color:#86efac}.li-fail .li-title{background:#450a0a;color:#fca5a5}
.li-out{padding:7px 11px;font-size:10px;color:#475569;background:#060a0f;white-space:pre-wrap;word-break:break-all;max-height:100px;overflow-y:auto}
.code-block{background:#060a0f;border:1px solid #1e293b;border-radius:7px;padding:11px 14px;font-size:11px;color:#a3e635;word-break:break-all;white-space:pre-wrap;margin-bottom:8px;position:relative}
.copy-btn{position:absolute;top:7px;right:8px;font-size:9px;padding:2px 8px;background:#1e293b;color:#64748b;border:1px solid #334155;border-radius:4px;cursor:pointer;font-family:inherit}
.copy-btn:hover{background:#334155;color:#e2e8f0}
.warn{background:#451a03;border:1px solid #92400e;border-radius:7px;padding:10px 14px;font-size:11px;color:#fdba74;margin-top:14px;line-height:1.6}
.detect-badge{font-size:9px;padding:1px 7px;border-radius:99px;background:#0c1a2e;color:#60a5fa;border:1px solid #1e3a5f;margin-left:5px}
hr{border:none;border-top:1px solid #1e293b;margin:18px 0}
</style>
</head>
<body>
<div class="card">

<div class="hdr">
    <h1>🔥 FlamingDragon Installer</h1>
    <p>Zero-configuration setup wizard · Delete this file after installation</p>
</div>

<?php /* Progress */ ?>
<div class="prog">
<?php foreach ($allSteps as $i => $s):
    $idx = array_search($step, $allSteps);
    $cls = $i < $idx ? 'done' : ($i === $idx ? 'active' : '');
    echo "<span class=\"ps $cls\">" . ($i < $idx ? '✓ ' : '') . htmlspecialchars($stepLabels[$s]) . '</span>';
endforeach ?>
</div>

<div class="body">

<?php if ($errors): ?>
<ul class="errs"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach ?></ul>
<?php endif ?>

<?php /* ════════════════════════════════════ REQUIREMENTS */ if ($step === 'requirements'): ?>
<?php $reqs = fd_requirements(); ?>
<h2>System Requirements</h2>
<p class="sub">Checking your server before we start.</p>

<?php foreach ($reqs as $r): ?>
<div class="req <?= $r['ok'] ? 'req-ok' : 'req-fail' ?>">
    <span class="<?= $r['ok'] ? 'lbl-ok' : 'lbl-fail' ?>"><?= $r['ok'] ? '✓' : '✗' ?> <?= htmlspecialchars($r['label']) ?></span>
    <span class="rval"><?= htmlspecialchars($r['value']) ?></span>
</div>
<?php endforeach ?>

<?php if ($project): ?>
<div class="info" style="margin-top:14px">
    <strong>Project detected at:</strong> <code><?= htmlspecialchars($project) ?></code><br>
    Clone step will be skipped — installer will configure the existing project.
</div>
<?php endif ?>

<form method="POST">
<input type="hidden" name="action" value="req_next">
<div class="actions"><span></span>
<button type="submit" class="btn btn-p" <?= fd_all_ok($reqs) ? '' : 'disabled style="opacity:.35;cursor:not-allowed"' ?>>Continue →</button>
</div></form>

<?php /* ════════════════════════════════════ SOURCE */ elseif ($step === 'source'): ?>
<h2>Source Repository</h2>
<p class="sub">Where to download FlamingDragon from.</p>

<form method="POST">
<input type="hidden" name="action" value="source_next">
<div class="field">
    <label>Git Repository URL</label>
    <input type="text" name="git_url" value="<?= htmlspecialchars($data['git_url'] ?? '') ?>" placeholder="https://github.com/you/flamingdragon.git">
</div>
<div class="row2">
    <div class="field"><label>Branch</label><input type="text" name="branch" value="<?= htmlspecialchars($data['branch'] ?? 'main') ?>"></div>
    <div class="field">
        <label>Install Path</label>
        <input type="text" name="install_path" value="<?= htmlspecialchars($data['project_root'] ?? $webRootDef . '/flamingdragon') ?>">
        <div class="hint">Detected web root: <code><?= htmlspecialchars($webRootDef) ?></code></div>
    </div>
</div>
<div class="actions">
    <button type="submit" name="action" value="back" formnovalidate class="btn btn-s">← Back</button>
    <button type="submit" class="btn btn-p">Continue →</button>
</div></form>

<?php /* ════════════════════════════════════ DASHBOARD ACCESS */ elseif ($step === 'dashboard'): ?>
<h2>Dashboard Access</h2>
<p class="sub">How do you want to reach the FlamingDragon dashboard from your browser?</p>

<?php
$da = $data['dashboard'] ?? [];
$selMode = $da['mode'] ?? 'port';
?>

<form method="POST" id="daForm">
<input type="hidden" name="action" value="dashboard_next">

<div class="radio-group">
    <label class="radio-card <?= $selMode==='domain'?'selected':'' ?>" onclick="selectMode('domain')">
        <input type="radio" name="access_mode" value="domain" <?= $selMode==='domain'?'checked':'' ?>>
        <h3>🌐 Domain</h3>
        <p>Custom domain like <code>admin.mysite.com</code>. Generates a VirtualHost / server block.</p>
    </label>
    <label class="radio-card <?= $selMode==='port'?'selected':'' ?>" onclick="selectMode('port')">
        <input type="radio" name="access_mode" value="port" <?= $selMode==='port'?'checked':'' ?>>
        <h3>🔌 Port <span class="tag tag-auto">auto-detect</span></h3>
        <p>Access via <code>http://host:PORT/</code>. Scans for a free port automatically.</p>
    </label>
    <label class="radio-card <?= $selMode==='subdir'?'selected':'' ?>" onclick="selectMode('subdir')">
        <input type="radio" name="access_mode" value="subdir" <?= $selMode==='subdir'?'checked':'' ?>>
        <h3>📁 Subdirectory</h3>
        <p>Serve from <code>http://host/flamingdragon/</code> — no web server changes needed.</p>
    </label>
</div>

{{-- Domain --}}
<div id="f-domain" style="display:<?= $selMode==='domain'?'block':'none' ?>">
    <div class="field"><label>Domain name</label>
        <input type="text" name="domain" value="<?= htmlspecialchars($da['domain'] ?? '') ?>" placeholder="admin.myserver.com">
        <div class="hint">Must point to this server's IP. Use without http://</div>
    </div>
</div>

{{-- Port --}}
<div id="f-port" style="display:<?= $selMode==='port'?'block':'none' ?>">
    <div class="field"><label>Port <span class="detect-badge">first free port detected</span></label>
        <input type="number" name="port" value="<?= htmlspecialchars((string)($da['port'] ?? $freePort ?? 8080)) ?>" min="1024" max="65535">
        <div class="hint">Port <?= htmlspecialchars((string)($freePort ?? 'n/a')) ?> is free on this server. Change if needed.</div>
    </div>
    <div class="info">
        <strong>URL will be:</strong> <code>http://<?= htmlspecialchars($currentHost) ?>:<?= htmlspecialchars((string)($da['port'] ?? $freePort ?? 8080)) ?>/dashboard</code><br>
        The installer will generate a VirtualHost / Nginx block and try to install it automatically.
        If it cannot (no write access to config dir), it will show you the config to paste manually.<br>
        <strong>Fallback:</strong> if no web server config can be installed, a systemd unit for
        <code>php artisan serve</code> will be generated.
    </div>
</div>

{{-- Subdir --}}
<div id="f-subdir" style="display:<?= $selMode==='subdir'?'block':'none' ?>">
    <div class="field"><label>Subdirectory name</label>
        <input type="text" name="subdir" value="<?= htmlspecialchars($da['subdir'] ?? 'flamingdragon') ?>" placeholder="flamingdragon">
        <div class="hint">The installer creates a symlink: <code><?= htmlspecialchars($webRootDef) ?>/flamingdragon → project/public/</code></div>
    </div>
    <div class="info">
        <strong>URL will be:</strong> <code>http://<?= htmlspecialchars($currentHost) ?>/flamingdragon/dashboard</code>
    </div>
</div>

<hr>

<div class="row2">
    <div class="field">
        <label>Web Server <span class="detect-badge">detected: <?= htmlspecialchars($wsDefault) ?></span></label>
        <select name="webserver">
            <option value="apache" <?= ($da['webserver']??$wsDefault)==='apache'?'selected':'' ?>>Apache</option>
            <option value="nginx"  <?= ($da['webserver']??$wsDefault)==='nginx' ?'selected':'' ?>>Nginx</option>
            <option value="unknown"<?= ($da['webserver']??$wsDefault)==='unknown'?'selected':'' ?>>Unknown / Other</option>
        </select>
    </div>
    <div class="field">
        <label>PHP Binary <span class="detect-badge">detected</span></label>
        <input type="text" name="php_bin" value="<?= htmlspecialchars($da['php_bin'] ?? $phpBinDef) ?>">
        <div class="hint">Used to run <code>artisan</code> commands.</div>
    </div>
</div>

<div class="actions">
    <button type="submit" name="action" value="back" formnovalidate class="btn btn-s">← Back</button>
    <button type="submit" class="btn btn-p">Continue →</button>
</div></form>

<script>
function selectMode(m){
    ['domain','port','subdir'].forEach(x=>{
        document.getElementById('f-'+x).style.display = x===m?'':'none';
        document.querySelector('.radio-card[onclick="selectMode(\''+x+'\')"]').classList.toggle('selected',x===m);
    });
    document.querySelector('input[name=access_mode][value='+m+']').checked=true;
}
</script>

<?php /* ════════════════════════════════════ DATABASE */ elseif ($step === 'database'): ?>
<h2>Database</h2>
<p class="sub">MariaDB / MySQL connection. The database must already exist.<br>
<span class="detect-badge" style="font-size:10px"><?= $dbDef['found'] ? '✓ Auto-detected at '.$dbDef['host'].':'.$dbDef['port'] : 'No running DB detected — fill manually' ?></span></p>

<form method="POST">
<input type="hidden" name="action" value="db_next">
<div class="row3">
    <div class="field"><label>Host</label>
        <input type="text" name="db_host" value="<?= htmlspecialchars($data['db']['host'] ?? $dbDef['host']) ?>">
    </div>
    <div class="field"><label>Port</label>
        <input type="text" name="db_port" value="<?= htmlspecialchars($data['db']['port'] ?? $dbDef['port']) ?>">
    </div>
    <div class="field"><label>Database</label>
        <input type="text" name="db_database" value="<?= htmlspecialchars($data['db']['database'] ?? 'flamingdragon') ?>">
    </div>
</div>
<div class="row2">
    <div class="field"><label>Username</label>
        <input type="text" name="db_username" value="<?= htmlspecialchars($data['db']['username'] ?? '') ?>">
    </div>
    <div class="field"><label>Password</label>
        <input type="password" name="db_password" value="">
        <div class="hint">Connection is tested before continuing.</div>
    </div>
</div>
<div class="hint" style="margin-bottom:10px">Create DB if needed: <code>CREATE DATABASE flamingdragon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</code></div>
<div class="actions">
    <button type="submit" name="action" value="back" formnovalidate class="btn btn-s">← Back</button>
    <button type="submit" class="btn btn-p">Test & Continue →</button>
</div></form>

<?php /* ════════════════════════════════════ TELEGRAM */ elseif ($step === 'telegram'): ?>
<h2>Telegram Bot</h2>
<p class="sub">The bot receives your commands and sends back results.</p>
<div class="info">
    <strong>Create a bot:</strong> open Telegram → <code>@BotFather</code> → <code>/newbot</code> → copy the token.<br>
    <strong>Get your chat ID:</strong> start the bot, then open <code>https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code> — look for <code>"id"</code> inside <code>"chat"</code>.
</div>
<form method="POST">
<input type="hidden" name="action" value="tg_next">
<div class="field"><label>Bot Token</label>
    <input type="text" name="tg_token" value="<?= htmlspecialchars($data['telegram']['token'] ?? '') ?>" placeholder="1234567890:AAF...">
</div>
<div class="field"><label>Webhook Secret</label>
    <input type="text" name="tg_secret" value="<?= htmlspecialchars($data['telegram']['secret'] ?? fd_random()) ?>">
    <div class="hint">Random string verifying calls come from Telegram. Pre-filled — leave as is.</div>
</div>
<div class="field"><label>Allowed Chat IDs <span class="tag">optional now</span></label>
    <input type="text" name="tg_chat_ids" value="<?= htmlspecialchars($data['telegram']['chat_ids'] ?? '') ?>" placeholder="82472920">
    <div class="hint">Comma-separated. Leave empty and set via the Setup Wizard in the dashboard later.</div>
</div>
<div class="actions">
    <button type="submit" name="action" value="back" formnovalidate class="btn btn-s">← Back</button>
    <button type="submit" class="btn btn-p">Continue →</button>
</div></form>

<?php /* ════════════════════════════════════ LLM */ elseif ($step === 'llm'): ?>
<?php $selProv = $data['llm']['provider'] ?? 'anthropic'; $selMod = $data['llm']['model'] ?? 'claude-haiku-4-5-20251001'; ?>
<h2>LLM Provider</h2>
<p class="sub">The AI model powering the agent.</p>
<form method="POST" id="llmF">
<input type="hidden" name="action" value="llm_next">
<div class="row2">
<div class="field"><label>Provider</label>
    <select name="llm_provider" id="pSel" onchange="syncM()">
        <option value="anthropic" <?= $selProv==='anthropic'?'selected':'' ?>>Anthropic (Claude)</option>
        <option value="openai"    <?= $selProv==='openai'   ?'selected':'' ?>>OpenAI (GPT)</option>
        <option value="ollama"    <?= $selProv==='ollama'   ?'selected':'' ?>>Ollama (local)</option>
    </select>
</div>
<div class="field" id="mDrop"><label>Model</label>
    <select name="llm_model" id="mSel">
        <?php foreach (MODELS_INS as $pv => $ms): foreach ($ms as $val => $lbl): ?>
        <option value="<?= htmlspecialchars($val) ?>" data-p="<?= htmlspecialchars($pv) ?>"
            <?= $val===$selMod?'selected':'' ?> style="display:<?= $pv===$selProv?'':'none' ?>">
            <?= htmlspecialchars($lbl) ?>
        </option>
        <?php endforeach; endforeach ?>
    </select>
</div>
</div>
<div class="field" id="mText" style="display:none"><label>Model name (Ollama)</label>
    <input type="text" id="mOllama" placeholder="llama3, mistral, phi3…" value="<?= $selProv==='ollama'?htmlspecialchars($selMod):'' ?>">
    <div class="hint">Exact name of a model pulled with <code>ollama pull</code></div>
</div>
<div class="field" id="fAnt" style="display:<?= $selProv==='anthropic'?'block':'none' ?>"><label>Anthropic API Key</label>
    <input type="password" name="anthropic_key" value="<?= htmlspecialchars($data['llm']['anthropic_key'] ?? '') ?>" placeholder="sk-ant-api03-...">
    <div class="hint">console.anthropic.com → API Keys</div>
</div>
<div class="field" id="fOai" style="display:<?= $selProv==='openai'?'block':'none' ?>"><label>OpenAI API Key</label>
    <input type="password" name="openai_key" value="<?= htmlspecialchars($data['llm']['openai_key'] ?? '') ?>" placeholder="sk-...">
    <div class="hint">platform.openai.com/api-keys · Also used for semantic memory embeddings</div>
</div>
<div class="field" id="fOll" style="display:<?= $selProv==='ollama'?'block':'none' ?>"><label>Ollama URL</label>
    <input type="text" name="ollama_url" value="<?= htmlspecialchars($data['llm']['ollama_url'] ?? 'http://localhost:11434') ?>">
</div>
<div class="actions">
    <button type="submit" name="action" value="back" formnovalidate class="btn btn-s">← Back</button>
    <button type="submit" class="btn btn-p">Continue →</button>
</div></form>
<script>
function syncM(){
    const p=document.getElementById('pSel').value;
    const opts=document.getElementById('mSel').querySelectorAll('option');
    let first=null;
    opts.forEach(o=>{const show=o.dataset.p===p;o.style.display=show?'':'none';if(show&&!first)first=o;});
    if(first)document.getElementById('mSel').value=first.value;
    document.getElementById('fAnt').style.display=p==='anthropic'?'':'none';
    document.getElementById('fOai').style.display=p==='openai'?'':'none';
    document.getElementById('fOll').style.display=p==='ollama'?'':'none';
    document.getElementById('mDrop').style.display=p!=='ollama'?'':'none';
    document.getElementById('mText').style.display=p==='ollama'?'':'none';
}
document.getElementById('llmF').addEventListener('submit',()=>{
    if(document.getElementById('pSel').value==='ollama'){
        const v=document.getElementById('mOllama').value;
        document.getElementById('mSel').innerHTML=`<option value="${v}" selected>${v}</option>`;
    }
});
syncM();
</script>

<?php /* ════════════════════════════════════ INSTALL */ elseif ($step === 'install'): ?>
<?php $da=$data['dashboard']??[]; $db=$data['db']??[]; $tg=$data['telegram']??[]; $llm=$data['llm']??[]; ?>
<h2>Review & Install</h2>
<p class="sub">Check the settings below, then hit the button. Grab a coffee ☕</p>

<div style="background:#060a0f;border:1px solid #1e293b;border-radius:8px;padding:13px 16px;font-size:11px;line-height:2;margin-bottom:18px">
    <?php if(!$project): ?>
    <div><span style="color:#475569;display:inline-block;width:150px">Repository</span> <code style="color:#f97316"><?= htmlspecialchars($data['git_url']??'') ?></code></div>
    <div><span style="color:#475569;display:inline-block;width:150px">Install path</span> <code><?= htmlspecialchars($data['project_root']??'') ?></code></div>
    <?php else: ?>
    <div><span style="color:#475569;display:inline-block;width:150px">Project</span> <code style="color:#f97316"><?= htmlspecialchars($project) ?></code></div>
    <?php endif ?>
    <div><span style="color:#475569;display:inline-block;width:150px">Dashboard</span> <code><?= htmlspecialchars($da['app_url']??'') ?>/dashboard</code></div>
    <div><span style="color:#475569;display:inline-block;width:150px">Web server</span> <code><?= htmlspecialchars($da['webserver']??'') ?> · <?= htmlspecialchars($da['mode']??'') ?> mode</code></div>
    <div><span style="color:#475569;display:inline-block;width:150px">Database</span> <code><?= htmlspecialchars($db['username']??'') ?>@<?= htmlspecialchars($db['host']??'') ?>/<?= htmlspecialchars($db['database']??'') ?></code></div>
    <div><span style="color:#475569;display:inline-block;width:150px">LLM</span> <code><?= htmlspecialchars($llm['provider']??'') ?> / <?= htmlspecialchars($llm['model']??'') ?></code></div>
    <div><span style="color:#475569;display:inline-block;width:150px">Telegram token</span> <code><?= htmlspecialchars(substr($tg['token']??'',0,14)) ?>…</code></div>
</div>

<form method="POST">
<input type="hidden" name="action" value="run_install">
<div class="actions">
    <button type="submit" name="action" value="back" formnovalidate class="btn btn-s">← Back</button>
    <button type="submit" class="btn btn-p">🚀 Install FlamingDragon</button>
</div></form>

<?php /* ════════════════════════════════════ DONE */ elseif ($step === 'done'): ?>
<?php
$log        = $_SESSION['fd_log']          ?? [];
$allOk      = $_SESSION['fd_ok']           ?? false;
$wsConf     = $_SESSION['fd_ws_conf']      ?? '';
$wsFile     = $_SESSION['fd_ws_file']      ?? '';
$wsInst     = $_SESSION['fd_ws_installed'] ?? false;
$systemd    = $_SESSION['fd_systemd']      ?? '';
$root       = $data['project_root']        ?? ($project ?? '');
$da         = $data['dashboard']           ?? [];
$tg         = $data['telegram']            ?? [];
$appUrl     = $da['app_url']               ?? 'http://localhost';
$phpBin     = $da['php_bin']               ?? 'php';
$port       = $da['port']                  ?? null;
?>

<div style="font-size:40px;text-align:center;margin-bottom:14px"><?= $allOk ? '✅' : '❌' ?></div>
<h2 style="text-align:center;margin-bottom:4px"><?= $allOk ? 'Installation complete!' : 'Installation failed' ?></h2>
<p class="sub" style="text-align:center;margin-bottom:18px"><?= $allOk ? 'FlamingDragon is installed and configured.' : 'Some steps failed — check the log.' ?></p>

<?php foreach ($log as $item): ?>
<div class="li <?= $item['ok']?'li-ok':'li-fail' ?>">
    <div class="li-title"><?= $item['ok']?'✓':'✗' ?> <?= htmlspecialchars($item['title']) ?></div>
    <?php if($item['out']!==''): ?><div class="li-out"><?= htmlspecialchars($item['out']) ?></div><?php endif ?>
</div>
<?php endforeach ?>

<?php if ($allOk): ?>
<hr>
<h2 style="margin-bottom:10px">Your dashboard</h2>
<div class="code-block"><a href="<?= htmlspecialchars($appUrl) ?>/dashboard" target="_blank" style="color:#a3e635"><?= htmlspecialchars($appUrl) ?>/dashboard</a></div>

<?php if ($wsConf !== '' && ! $wsInst): ?>
<h2 style="margin:14px 0 8px">Web server config <span class="tag">manual install needed</span></h2>
<p class="hint" style="margin-bottom:8px">The installer could not write to <code><?= htmlspecialchars($wsFile) ?></code>. Paste this config manually:</p>
<div class="code-block" style="position:relative">
<button class="copy-btn" onclick="navigator.clipboard.writeText(document.getElementById('wsc').innerText);this.innerText='Copied!'">Copy</button>
<span id="wsc"><?= htmlspecialchars($wsConf) ?></span></div>
<?php if(str_contains($wsFile, 'apache')): ?>
<p class="hint">Then run: <code>a2ensite flamingdragon.conf &amp;&amp; systemctl reload apache2</code></p>
<?php elseif(str_contains($wsFile, 'nginx')): ?>
<p class="hint">Then run: <code>ln -s <?= htmlspecialchars($wsFile) ?> /etc/nginx/sites-enabled/ &amp;&amp; nginx -t &amp;&amp; systemctl reload nginx</code></p>
<?php endif ?>
<?php endif ?>

<?php if ($systemd !== ''): ?>
<h2 style="margin:14px 0 8px">Start with systemd <span class="tag">alternative to web server config</span></h2>
<div class="code-block" style="position:relative">
<button class="copy-btn" onclick="navigator.clipboard.writeText(document.getElementById('sdu').innerText);this.innerText='Copied!'">Copy</button>
<span id="sdu"><?= htmlspecialchars($systemd) ?></span></div>
<p class="hint">Save to <code>/etc/systemd/system/flamingdragon.service</code>, then:<br>
<code>systemctl daemon-reload &amp;&amp; systemctl enable --now flamingdragon</code></p>
<?php endif ?>

<hr>
<h2 style="margin-bottom:8px">Register Telegram Webhook</h2>
<div class="code-block" style="position:relative">
<button class="copy-btn" onclick="navigator.clipboard.writeText(document.getElementById('whcmd').innerText);this.innerText='Copied!'">Copy</button>
<span id="whcmd">curl -X POST "https://api.telegram.org/bot<?= htmlspecialchars($tg['token']??'TOKEN') ?>/setWebhook" \
  --data-urlencode "url=<?= htmlspecialchars($appUrl) ?>/api/telegram/webhook" \
  --data-urlencode "secret_token=<?= htmlspecialchars($tg['secret']??'SECRET') ?>"</span></div>
<p class="hint" style="margin-bottom:14px">Or use the Setup Wizard in the dashboard → it does this for you.</p>

<h2 style="margin-bottom:8px">Start the queue worker</h2>
<div class="code-block">cd <?= htmlspecialchars($root) ?> &amp;&amp; <?= htmlspecialchars($phpBin) ?> artisan queue:work --tries=3 --timeout=300</div>
<p class="hint">For production, run this as a supervised process (systemd, supervisor, screen).</p>
<?php endif ?>

<div class="warn">
    ⚠ <strong>Delete this file now!</strong> It exposes your credentials and server internals.<br>
    <code>rm <?= htmlspecialchars(__FILE__) ?></code>
</div>

<div class="actions" style="margin-top:14px">
    <?php if(!$allOk): ?>
    <form method="POST"><button type="submit" name="action" value="back" class="btn btn-s">← Back & Fix</button></form>
    <?php else: ?><span></span><?php endif ?>
    <form method="POST"><button type="submit" name="action" value="reset" class="btn btn-d">Start over</button></form>
</div>

<?php endif ?>
</div><!-- /.body -->
</div><!-- /.card -->
<p style="color:#1e293b;font-size:10px;margin-top:14px">FlamingDragon Installer · session data is local only</p>
</body>
</html>
