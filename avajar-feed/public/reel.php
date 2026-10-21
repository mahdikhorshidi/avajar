<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

send_common_headers();
header('Content-Type: text/html; charset=utf-8');

$slug = isset($_GET['slug']) ? (string) $_GET['slug'] : '';
$row = preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) ? find_reel_by_slug($slug) : null;
$siteName = config('SITE_NAME');

if ($row === null) {
    http_response_code(404);
    ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>یافت نشد | <?= e($siteName) ?></title>
<link rel="stylesheet" href="<?= e(asset_url('feed.css')) ?>">
</head>
<body class="page-404">
<main class="notfound">
  <h1>این ریلز پیدا نشد</h1>
  <p><a href="./">بازگشت به ریلز</a></p>
</main>
</body>
</html>
<?php
    exit;
}

$reel = reel_public($row);
$canonical = site_url($reel['url']);
$description = excerpt($reel['caption'] !== '' ? $reel['caption'] : $reel['title']);
$uploadDate = (new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')))->format(DATE_ATOM);

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'VideoObject',
    'name' => $reel['title'],
    'description' => $description,
    'thumbnailUrl' => [$reel['poster']],
    'uploadDate' => $uploadDate,
    'duration' => iso_duration($reel['duration']),
    'contentUrl' => $reel['sources']['720'],
    'url' => $canonical,
    'width' => 720,
    'height' => 1280,
    'inLanguage' => 'fa',
    'publisher' => [
        '@type' => 'Organization',
        'name' => $siteName,
        'url' => site_url(''),
    ],
];
// JSON_HEX_TAG keeps "</script>" in user text from closing the tag.
$jsonLdOut = json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($reel['title']) ?> | <?= e($siteName) ?></title>
<meta name="description" content="<?= e($description) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:type" content="video.other">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:locale" content="fa_IR">
<meta property="og:title" content="<?= e($reel['title']) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($reel['poster']) ?>">
<meta property="og:image:width" content="720">
<meta property="og:image:height" content="1280">
<meta property="og:video" content="<?= e($reel['sources']['720']) ?>">
<meta property="og:video:secure_url" content="<?= e($reel['sources']['720']) ?>">
<meta property="og:video:type" content="video/mp4">
<meta property="og:video:width" content="720">
<meta property="og:video:height" content="1280">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#000000">
<link rel="stylesheet" href="<?= e(asset_url('feed.css')) ?>">
<script type="application/ld+json"><?= $jsonLdOut ?></script>
</head>
<body>
<header class="brand"><a class="brand__name" href="./"><?= e($siteName) ?></a></header>

<main id="feed" class="feed feed--single">
<?= render_reel($reel, true, true) ?>
</main>

<a class="cta" href="<?= e(config('CTA_URL')) ?>"><?= e(config('CTA_LABEL')) ?></a>

<script src="<?= e(asset_url('feed.js')) ?>" defer></script>
</body>
</html>
