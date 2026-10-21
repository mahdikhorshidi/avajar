<?php
declare(strict_types=1);

const BASE_DIR = __DIR__ . '/..';

/** Read a config value from config.php (loaded once). */
function config(string $key): string
{
    static $config = null;
    if ($config === null) {
        $file = BASE_DIR . '/config.php';
        if (!is_file($file)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            exit("config.php is missing. Copy config.php.example to config.php and fill it in.\n");
        }
        $config = require $file;
    }
    return isset($config[$key]) ? (string) $config[$key] : '';
}

/** Shared PDO connection. Creates and seeds the DB on first use. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $path = config('DB_PATH') !== '' ? config('DB_PATH') : BASE_DIR . '/data/avajar.sqlite';
    $isNew = !is_file($path);

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 5000');

    if ($isNew) {
        $pdo->beginTransaction();
        $pdo->exec((string) file_get_contents(BASE_DIR . '/schema.sql'));
        $pdo->exec((string) file_get_contents(BASE_DIR . '/seed.sql'));
        $pdo->commit();
    }
    return $pdo;
}

/** Escape for HTML text and attribute context. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Absolute URL on the site, from SITE_URL. */
function site_url(string $path = ''): string
{
    return rtrim(config('SITE_URL'), '/') . '/' . ltrim($path, '/');
}

/** Resolve a stored media reference (absolute URL or CDN-relative path). */
function media_url(string $ref): string
{
    if (preg_match('#^https?://#i', $ref)) {
        return $ref;
    }
    return rtrim(config('CDN_BASE_URL'), '/') . '/' . ltrim($ref, '/');
}

/** Relative URL to a file in public/assets with a cache-busting version. */
function asset_url(string $file): string
{
    $full = BASE_DIR . '/public/assets/' . $file;
    $version = is_file($full) ? (string) filemtime($full) : '0';
    return 'assets/' . $file . '?v=' . $version;
}

function str_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : (int) preg_match_all('/./us', $value);
}

/** Single-line excerpt for meta descriptions. */
function excerpt(string $text, int $max = 155): string
{
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));
    if (str_length($text) <= $max) {
        return $text;
    }
    $cut = function_exists('mb_substr') ? mb_substr($text, 0, $max - 1, 'UTF-8') : (string) preg_replace('/^(.{' . ($max - 1) . '}).*$/us', '$1', $text);
    return rtrim($cut) . '…';
}

/** ISO 8601 duration, e.g. 75 -> PT1M15S. */
function iso_duration(int $seconds): string
{
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    return 'PT' . ($m > 0 ? $m . 'M' : '') . $s . 'S';
}

function send_common_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
}

require __DIR__ . '/reels.php';
