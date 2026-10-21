<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function admin_start(): void
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $dir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') . '/';

    session_name('avajar_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $dir,
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();

    send_common_headers();
    header('X-Frame-Options: DENY');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=utf-8');
}

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin']);
}

function admin_require_login(): void
{
    if (!admin_logged_in()) {
        redirect('login.php');
    }
}

function redirect(string $location): void
{
    header('Location: ' . $location, true, 303);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Rejects any POST without a valid CSRF token. */
function csrf_verify(): void
{
    $sent = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
    if ($sent === '' || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(400);
        exit('Invalid CSRF token. Reload the page and try again.');
    }
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method not allowed');
    }
    csrf_verify();
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $msg = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $msg;
}

/** Absolute http(s) URL or a CDN-relative path like reels/x/360.mp4. */
function valid_media_ref(string $value): bool
{
    if ($value === '' || strlen($value) > 500) {
        return false;
    }
    if (preg_match('#^https?://#i', $value)) {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
    return (bool) preg_match('#^[A-Za-z0-9][A-Za-z0-9._~/-]*$#', $value)
        && !str_contains($value, '..')
        && !str_contains($value, '//');
}

function admin_header(string $title): void
{
    ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> | مدیریت ریلز</title>
<style>
  body { margin: 0; font-family: Vazirmatn, Vazir, Tahoma, "Segoe UI", system-ui, sans-serif; background: #f5f5f5; color: #222; line-height: 1.7; }
  header { display: flex; gap: 12px; align-items: center; justify-content: space-between; padding: 12px 16px; background: #111; color: #fff; }
  header a, header button { color: #fff; }
  main { max-width: 960px; margin: 0 auto; padding: 16px; }
  table { width: 100%; border-collapse: collapse; background: #fff; }
  th, td { padding: 8px; border-bottom: 1px solid #ddd; text-align: start; vertical-align: top; }
  form.inline { display: inline; }
  label { display: block; margin-top: 12px; font-weight: 600; }
  input[type=text], input[type=password], input[type=number], input[type=url], textarea { width: 100%; box-sizing: border-box; padding: 8px; font: inherit; border: 1px solid #bbb; border-radius: 4px; }
  input[dir=ltr] { text-align: left; }
  textarea { min-height: 140px; }
  button, .btn { display: inline-block; padding: 8px 14px; font: inherit; border: 0; border-radius: 4px; background: #111; color: #fff; cursor: pointer; text-decoration: none; }
  button.danger { background: #b00020; }
  button.link { background: none; padding: 0; text-decoration: underline; }
  .flash { padding: 10px; background: #e6f4ea; border: 1px solid #9ccc9c; }
  .errors { padding: 10px; background: #fdecea; border: 1px solid #e0a0a0; }
  small { color: #666; }
</style>
</head>
<body>
<header>
  <strong>مدیریت ریلز</strong>
  <?php if (admin_logged_in()): ?>
  <span>
    <a href="index.php">فهرست</a> ·
    <a href="edit.php">ریلز جدید</a> ·
    <form class="inline" method="post" action="logout.php"><?= csrf_field() ?><button class="link" type="submit">خروج</button></form>
  </span>
  <?php endif; ?>
</header>
<main>
<?php
    $msg = flash();
    if ($msg !== null) {
        echo '<p class="flash">' . e($msg) . '</p>';
    }
}

function admin_footer(): void
{
    echo "</main>\n</body>\n</html>\n";
}
