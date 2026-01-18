SET search_path TO collections;

-- ----------------------------
-- Album table (master release, grouped by master_id)
-- ----------------------------
CREATE TABLE album (
    id                  BIGSERIAL PRIMARY KEY,
    master_id           INTEGER,           -- Discogs master_id (NULL if no master)
    title               TEXT NOT NULL,
    normalized_title    TEXT NOT NULL,     -- For search/normalization if needed later
    year                INTEGER,           -- Release year
    thumb_url           TEXT,              -- Thumbnail image URL
    cover_image_url     TEXT               -- Full cover image URL
);

CREATE INDEX idx_album_master_id ON album(master_id);
CREATE INDEX idx_album_title ON album(title);
CREATE INDEX idx_album_year ON album(year);
CREATE UNIQUE INDEX ux_album_master_id ON album(master_id) WHERE master_id IS NOT NULL;

-- ----------------------------
-- Release table (physical release instance)
-- ----------------------------
CREATE TABLE release (
    id                      BIGSERIAL PRIMARY KEY,
    album_id                BIGINT NOT NULL REFERENCES album(id) ON DELETE CASCADE,
    discogs_id              INTEGER NOT NULL,              -- Discogs release ID
    discogs_instance_id     BIGINT NOT NULL UNIQUE,        -- Discogs instance_id (unique per collection item)
    date_added              TIMESTAMPTZ,                   -- When added to collection
    rating                  INTEGER,                       -- User rating (0-5)
    media_condition         TEXT,                          -- Condition enum (see constraint below)
    sleeve_condition        TEXT,                          -- Condition enum (see constraint below)
    resource_url            TEXT                           -- Discogs API resource URL
);

CREATE INDEX idx_release_album_id ON release(album_id);
CREATE INDEX idx_release_discogs_id ON release(discogs_id);
CREATE INDEX idx_release_discogs_instance_id ON release(discogs_instance_id);
CREATE INDEX idx_release_date_added ON release(date_added);
CREATE INDEX idx_release_rating ON release(rating);

-- Condition values constraint (media_condition and sleeve_condition)
-- Media: Mint (M), Near Mint (NM or M-), Very Good Plus (VG+), Very Good (VG), Good Plus (G+), Good (G), Fair (F), Poor (P)
-- Sleeve: Same as media, plus "Generic" and "No Cover". Both can be NULL.
ALTER TABLE release
    ADD CONSTRAINT chk_media_condition CHECK (
        media_condition IS NULL OR
        media_condition IN ('Mint (M)', 'Near Mint (NM or M-)', 'Very Good Plus (VG+)', 'Very Good (VG)', 'Good Plus (G+)', 'Good (G)', 'Fair (F)', 'Poor (P)')
    );

ALTER TABLE release
    ADD CONSTRAINT chk_sleeve_condition CHECK (
        sleeve_condition IS NULL OR
        sleeve_condition IN ('Mint (M)', 'Near Mint (NM or M-)', 'Very Good Plus (VG+)', 'Very Good (VG)', 'Good Plus (G+)', 'Good (G)', 'Fair (F)', 'Poor (P)', 'Generic', 'No Cover')
    );

-- ----------------------------
-- Junction tables for relationships
-- ----------------------------

-- Artists
CREATE TABLE release_artist (
    release_id    BIGINT NOT NULL REFERENCES release(id) ON DELETE CASCADE,
    artist_name   TEXT NOT NULL,
    sequence      INTEGER NOT NULL DEFAULT 1,  -- Order of artists on release
    
    PRIMARY KEY (release_id, sequence)
);

CREATE INDEX idx_release_artist_release_id ON release_artist(release_id);
CREATE INDEX idx_release_artist_artist_name ON release_artist(artist_name);

-- Labels
CREATE TABLE release_label (
    release_id    BIGINT NOT NULL REFERENCES release(id) ON DELETE CASCADE,
    label_name    TEXT NOT NULL,
    catalog_no    TEXT,                        -- Label catalog number
    sequence      INTEGER NOT NULL DEFAULT 1,  -- Order of labels on release
    
    PRIMARY KEY (release_id, sequence)
);

CREATE INDEX idx_release_label_release_id ON release_label(release_id);
CREATE INDEX idx_release_label_label_name ON release_label(label_name);

-- Genres
CREATE TABLE release_genre (
    release_id    BIGINT NOT NULL REFERENCES release(id) ON DELETE CASCADE,
    genre         TEXT NOT NULL,
    
    PRIMARY KEY (release_id, genre)
);

CREATE INDEX idx_release_genre_release_id ON release_genre(release_id);
CREATE INDEX idx_release_genre_genre ON release_genre(genre);

-- Styles
CREATE TABLE release_style (
    release_id    BIGINT NOT NULL REFERENCES release(id) ON DELETE CASCADE,
    style         TEXT NOT NULL,
    
    PRIMARY KEY (release_id, style)
);

CREATE INDEX idx_release_style_release_id ON release_style(release_id);
CREATE INDEX idx_release_style_style ON release_style(style);

-- Formats
CREATE TABLE release_format (
    release_id    BIGINT NOT NULL REFERENCES release(id) ON DELETE CASCADE,
    format_name   TEXT NOT NULL,               -- e.g., "Vinyl", "CD"
    quantity      TEXT,                        -- e.g., "1", "2"
    descriptions  TEXT,                        -- JSON array or comma-separated, e.g., "LP, Album, Stereo"
    sequence      INTEGER NOT NULL DEFAULT 1,  -- Order of formats on release
    
    PRIMARY KEY (release_id, sequence)
);

CREATE INDEX idx_release_format_release_id ON release_format(release_id);
CREATE INDEX idx_release_format_format_name ON release_format(format_name);
