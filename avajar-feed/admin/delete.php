<?php
declare(strict_types=1);

require __DIR__ . '/../src/admin.php';

admin_start();
admin_require_login();
require_post();

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id !== false) {
    $stmt = db()->prepare('DELETE FROM reels WHERE id = :id');
    $stmt->execute([':id' => $id]);
    flash($stmt->rowCount() > 0 ? 'ریلز حذف شد.' : 'ریلز پیدا نشد.');
}
redirect('index.php');
