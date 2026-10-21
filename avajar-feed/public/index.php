<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

send_common_headers();
header('Content-Type: text/html; charset=utf-8');

$page = fetch_feed_page(null);
$reels = array_map('reel_public', $page['items']);
$siteName = config('SITE_NAME');
$description = 'ریلز و نمونه‌کارهای ' . $siteName . ' در شبکه‌های اجتماعی و کپی‌رایتینگ.';
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($siteName) ?> | ریلز</title>
<meta name="description" content="<?= e($description) ?>">
<link rel="canonical" href="<?= e(site_url('')) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($siteName) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e(site_url('')) ?>">
<?php if ($reels): ?>
<meta property="og:image" content="<?= e($reels[0]['poster']) ?>">
<?php endif; ?>
<meta name="theme-color" content="#000000">
<link rel="stylesheet" href="<?= e(asset_url('feed.css')) ?>">
</head>
<body>
<header class="brand"><span class="brand__name"><?= e($siteName) ?></span></header>

<main id="feed" class="feed" data-api="api/feed.php" data-next-cursor="<?= e($page['next_cursor'] ?? '') ?>">
<?php foreach ($reels as $i => $reel): ?>
<?= render_reel($reel, $i === 0) ?>
<?php endforeach; ?>
<?php if (!$reels): ?>
<p class="feed__empty">هنوز ریلزی منتشر نشده است.</p>
<?php endif; ?>
</main>

<template id="reel-template">
<?= render_reel(['slug' => '', 'title' => '', 'caption' => '', 'url' => '', 'poster' => '', 'duration' => 0, 'sources' => ['360' => '', '540' => '', '720' => '']]) ?>
</template>

<a class="cta" href="<?= e(config('CTA_URL')) ?>"><?= e(config('CTA_LABEL')) ?></a>

<script src="<?= e(asset_url('feed.js')) ?>" defer></script>
</body>
</html>
