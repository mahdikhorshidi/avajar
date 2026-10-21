-- Avajar reels feed: SQLite schema.
-- Applied automatically on first request when the DB file does not exist yet.

CREATE TABLE IF NOT EXISTS reels (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    slug             TEXT    NOT NULL UNIQUE,
    title            TEXT    NOT NULL,
    caption          TEXT    NOT NULL DEFAULT '',
    poster_url       TEXT    NOT NULL,
    video_360_url    TEXT    NOT NULL,
    video_540_url    TEXT    NOT NULL,
    video_720_url    TEXT    NOT NULL,
    duration_seconds INTEGER NOT NULL CHECK (duration_seconds >= 0),
    sort_order       INTEGER NOT NULL DEFAULT 0,
    created_at       TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),
    updated_at       TEXT    NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
);

-- Feed order and cursor pagination: (sort_order, id) ascending.
CREATE INDEX IF NOT EXISTS idx_reels_feed ON reels (sort_order, id);
