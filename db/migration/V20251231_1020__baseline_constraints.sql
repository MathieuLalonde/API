SET search_path TO collections;

-- ----------------------------
-- film
-- ----------------------------

CREATE UNIQUE INDEX ux_film_identity
ON film (normalized_title, production_year);

-- ----------------------------
-- edition
-- ----------------------------
ALTER TABLE edition
    ADD CONSTRAINT fk_edition_film
    FOREIGN KEY (film_id) REFERENCES film(id)
    ON DELETE RESTRICT;

CREATE INDEX idx_edition_upc
    ON edition (upc);

-- Edition identity
CREATE UNIQUE INDEX idx_edition_external_id
    ON edition (external_id);

-- Orphan cleanup + joins
CREATE INDEX idx_edition_film_id
    ON edition (film_id);

-- ----------------------------
-- edition_region
-- ----------------------------
ALTER TABLE edition_region
    ADD CONSTRAINT chk_region_code
    CHECK (region_code ~ '^[0-6ABC]$');

CREATE INDEX idx_edition_region_code
    ON edition_region (region_code);

-- ----------------------------
-- audio_track
-- ----------------------------
CREATE INDEX idx_audio_track_edition_id
    ON audio_track (edition_id);

CREATE INDEX idx_audio_track_language
    ON audio_track (language);

-- ----------------------------
-- video_format
-- ----------------------------
CREATE UNIQUE INDEX idx_video_format_edition
    ON video_format (edition_id);
