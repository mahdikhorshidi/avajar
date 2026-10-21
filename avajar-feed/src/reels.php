<?php
declare(strict_types=1);

const FEED_PAGE_SIZE = 6;

/** Opaque cursor for keyset pagination over (sort_order, id). */
function encode_cursor(int $sortOrder, int $id): string
{
    return rtrim(strtr(base64_encode($sortOrder . ':' . $id), '+/', '-_'), '=');
}

/** @return array{0:int,1:int}|null */
function decode_cursor(string $cursor): ?array
{
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $cursor)) {
        return null;
    }
    $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
    if ($raw === false || !preg_match('/^(-?\d{1,9}):(\d{1,12})$/', $raw, $m)) {
        return null;
    }
    return [(int) $m[1], (int) $m[2]];
}

/**
 * One page of the feed.
 * @return array{items: list<array<string,mixed>>, next_cursor: ?string}
 */
function fetch_feed_page(?array $after, int $limit = FEED_PAGE_SIZE): array
{
    $sql = 'SELECT * FROM reels';
    $params = [];
    if ($after !== null) {
        $sql .= ' WHERE sort_order > :s1 OR (sort_order = :s2 AND id > :id)';
        $params = [':s1' => $after[0], ':s2' => $after[0], ':id' => $after[1]];
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC LIMIT :lim';

    $stmt = db()->prepare($sql);
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value, PDO::PARAM_INT);
    }
    // Fetch one extra row to know whether another page exists.
    $stmt->bindValue(':lim', $limit + 1, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $next = null;
    if (count($rows) > $limit) {
        $rows = array_slice($rows, 0, $limit);
        $last = $rows[$limit - 1];
        $next = encode_cursor((int) $last['sort_order'], (int) $last['id']);
    }
    return ['items' => $rows, 'next_cursor' => $next];
}

function find_reel_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM reels WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Public shape of a reel, shared by the API and the HTML views. */
function reel_public(array $row): array
{
    return [
        'slug' => (string) $row['slug'],
        'title' => (string) $row['title'],
        'caption' => (string) $row['caption'],
        'url' => 'reel.php?slug=' . rawurlencode((string) $row['slug']),
        'poster' => media_url((string) $row['poster_url']),
        'duration' => (int) $row['duration_seconds'],
        'sources' => [
            '360' => media_url((string) $row['video_360_url']),
            '540' => media_url((string) $row['video_540_url']),
            '720' => media_url((string) $row['video_720_url']),
        ],
    ];
}

/**
 * Markup for one reel. public/assets/feed.js builds the same structure
 * for items loaded from the API (see the <template> in index.php).
 */
function render_reel(array $reel, bool $eager = false, bool $titleIsH1 = false): string
{
    $tag = $titleIsH1 ? 'h1' : 'h2';
    $loadingAttrs = $eager ? 'loading="eager" fetchpriority="high"' : 'loading="lazy"';
    ob_start();
    ?>
<article class="reel" data-slug="<?= e($reel['slug']) ?>">
  <div class="reel__media">
    <img class="reel__poster" src="<?= e($reel['poster']) ?>" alt="" width="720" height="1280" <?= $loadingAttrs ?> decoding="async">
    <video class="reel__video" muted playsinline loop preload="none" disableremoteplayback
      data-src-360="<?= e($reel['sources']['360']) ?>"
      data-src-540="<?= e($reel['sources']['540']) ?>"
      data-src-720="<?= e($reel['sources']['720']) ?>"
      aria-label="<?= e($reel['title']) ?>"></video>
    <noscript><video class="reel__fallback" controls preload="none" playsinline poster="<?= e($reel['poster']) ?>" src="<?= e($reel['sources']['360']) ?>"></video></noscript>
    <span class="reel__play" aria-hidden="true" hidden></span>
  </div>
  <div class="reel__info">
    <<?= $tag ?> class="reel__title"><a href="<?= e($reel['url']) ?>"><?= e($reel['title']) ?></a></<?= $tag ?>>
    <p class="reel__caption"><?= e($reel['caption']) ?></p>
  </div>
  <button class="reel__sound" type="button" aria-pressed="false" aria-label="روشن کردن صدا"></button>
</article>
<?php
    return (string) ob_get_clean();
}
