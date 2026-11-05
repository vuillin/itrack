CREATE TABLE IF NOT EXISTS albums (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    artist TEXT,
    release_year INTEGER,
    cover TEXT,
    sort_title TEXT,
    mbid TEXT,
    genres TEXT,
    label TEXT,
    track_count INTEGER,
    overview TEXT,
    primary_type TEXT,
    secondary_types TEXT,
    first_release_date TEXT,
    disambiguation TEXT,
    tracks_json TEXT,
    mb_rating REAL,
    mb_rating_votes INTEGER,
    is_favorite INTEGER DEFAULT 0,
    vinyl INTEGER DEFAULT 0,
    cd INTEGER DEFAULT 0,
    cassette INTEGER DEFAULT 0,
    boxset INTEGER DEFAULT 0,
    last_listened_at TEXT,
    user_rating REAL
);

CREATE INDEX IF NOT EXISTS idx_albums_sort_title ON albums(sort_title);
CREATE INDEX IF NOT EXISTS idx_albums_is_favorite ON albums(is_favorite);
CREATE INDEX IF NOT EXISTS idx_albums_artist ON albums(artist);
CREATE INDEX IF NOT EXISTS idx_albums_mbid ON albums(mbid);

CREATE UNIQUE INDEX IF NOT EXISTS idx_albums_mbid_unique
    ON albums(mbid)
    WHERE mbid IS NOT NULL AND mbid != '';

CREATE UNIQUE INDEX IF NOT EXISTS idx_albums_cover_unique
    ON albums(cover)
    WHERE cover IS NOT NULL AND cover != '';
