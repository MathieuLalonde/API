CREATE SCHEMA IF NOT EXISTS collections;

SET search_path TO collections;

CREATE TABLE film (
    id                  BIGSERIAL PRIMARY KEY,
    title               TEXT NOT NULL,
    normalized_title    TEXT NOT NULL,
    production_year     INTEGER NOT NULL,
    sort_title          TEXT,
    original_title      TEXT,
    running_time_min    INTEGER
);

CREATE TABLE edition (
    id                  BIGSERIAL PRIMARY KEY,
    film_id             BIGINT NOT NULL REFERENCES film(id) ON DELETE CASCADE,
    external_id         TEXT NOT NULL,
    external_id_base    TEXT,
    external_id_type    TEXT,
    external_locality_id INTEGER,
    external_variant_num INTEGER,

    previous_external_id TEXT,

    upc                 TEXT,
    release_date        DATE,

    name                TEXT,
    distributor         TEXT,

    last_edited_at      TIMESTAMPTZ
);

CREATE TABLE edition_region (
    edition_id      BIGINT NOT NULL REFERENCES edition(id) ON DELETE CASCADE,
    region_code     TEXT NOT NULL,  -- '1','2','A','B','C'

    PRIMARY KEY (edition_id, region_code)
);

CREATE TABLE audio_track (
    id                  BIGSERIAL PRIMARY KEY,
    edition_id          BIGINT NOT NULL REFERENCES edition(id) ON DELETE CASCADE,

    language            TEXT,
    channel_layout      TEXT,   -- '5.1', '2.0', '3D'
    format              TEXT,   -- 'DTS-X', 'DTS', 'Dolby Digital'
    is_descriptive      BOOLEAN DEFAULT FALSE
);

CREATE TABLE video_format (
    edition_id              BIGINT PRIMARY KEY REFERENCES edition(id) ON DELETE CASCADE,

    -- color
    is_color                BOOLEAN,
    is_black_and_white      BOOLEAN,
    is_colorized            BOOLEAN,
    is_mixed_color          BOOLEAN,

    -- dimensions
    is_2d                   BOOLEAN,
    is_3d_anaglyph          BOOLEAN,
    is_3d_bluray            BOOLEAN,

    -- presentation
    is_16x9                 BOOLEAN,
    aspect_ratio            NUMERIC(4,2),
    is_full_frame           BOOLEAN,
    is_letterbox            BOOLEAN,
    is_pan_and_scan         BOOLEAN,

    -- physical / mastering
    is_dual_layered         BOOLEAN,
    is_dual_sided           BOOLEAN,

    -- standard
    video_standard          TEXT    -- NTSC | PAL
);
