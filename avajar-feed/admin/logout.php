<?php
declare(strict_types=1);

require __DIR__ . '/../src/admin.php';

admin_start();
require_post();

$_SESSION = [];
session_destroy();
redirect('login.php');
