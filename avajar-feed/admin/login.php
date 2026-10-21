<?php
declare(strict_types=1);

require __DIR__ . '/../src/admin.php';

admin_start();

if (admin_logged_in()) {
    redirect('index.php');
}

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $hash = config('ADMIN_PASSWORD_HASH');
    if ($password !== '' && $hash !== '' && password_verify($password, $hash)) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        unset($_SESSION['csrf']);
        redirect('index.php');
    }
    usleep(500000); // slow down guessing
    $error = 'رمز عبور نادرست است.';
}

admin_header('ورود');
?>
<h1>ورود</h1>
<?php if ($error !== null): ?>
<p class="errors"><?= e($error) ?></p>
<?php endif; ?>
<form method="post" action="login.php">
  <?= csrf_field() ?>
  <label for="password">رمز عبور</label>
  <input id="password" name="password" type="password" autocomplete="current-password" required autofocus>
  <p><button type="submit">ورود</button></p>
</form>
<?php
admin_footer();
