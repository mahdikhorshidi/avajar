<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

send_common_headers();
header('Content-Type: application/json; charset=utf-8');

$after = null;
$cursor = isset($_GET['cursor']) ? (string) $_GET['cursor'] : '';
if ($cursor !== '') {
    $after = decode_cursor($cursor);
    if ($after === null) {
        http_response_code(400);
        header('Cache-Control: no-store');
        echo json_encode(['error' => 'invalid_cursor']);
        exit;
    }
}

$page = fetch_feed_page($after);
header('Cache-Control: public, max-age=60');

echo json_encode([
    'items' => array_map('reel_public', $page['items']),
    'next_cursor' => $page['next_cursor'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
