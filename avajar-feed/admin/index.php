<?php
declare(strict_types=1);

require __DIR__ . '/../src/admin.php';

admin_start();
admin_require_login();

$reels = db()->query('SELECT id, slug, title, duration_seconds, sort_order FROM reels ORDER BY sort_order ASC, id ASC')->fetchAll();

admin_header('فهرست ریلز');
?>
<h1>ریلزها</h1>
<p><a class="btn" href="edit.php">+ ریلز جدید</a> <a href="<?= e(site_url('')) ?>" target="_blank" rel="noopener">مشاهده سایت</a></p>
<?php if (!$reels): ?>
<p>هنوز ریلزی ثبت نشده است.</p>
<?php else: ?>
<table>
  <thead>
    <tr><th>ترتیب</th><th>عنوان</th><th>slug</th><th>مدت (ثانیه)</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($reels as $reel): ?>
    <tr>
      <td><?= e((string) $reel['sort_order']) ?></td>
      <td><?= e($reel['title']) ?></td>
      <td dir="ltr"><?= e($reel['slug']) ?></td>
      <td><?= e((string) $reel['duration_seconds']) ?></td>
      <td>
        <a href="edit.php?id=<?= e((string) $reel['id']) ?>">ویرایش</a>
        <form class="inline" method="post" action="delete.php" onsubmit="return confirm('این ریلز حذف شود؟');">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e((string) $reel['id']) ?>">
          <button class="danger" type="submit">حذف</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php
admin_footer();
