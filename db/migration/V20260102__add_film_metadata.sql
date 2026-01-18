SET search_path TO collections;

-- ----------------------------
-- Film table: Add rating columns
-- ----------------------------
ALTER TABLE film
    ADD COLUMN rating_system TEXT,
    ADD COLUMN rating TEXT,
    ADD COLUMN rating_age INTEGER,
    ADD COLUMN rating_details TEXT;

-- ----------------------------
-- Edition table: Add case and other features columns
-- ----------------------------
ALTER TABLE edition
    ADD COLUMN case_type TEXT,
    ADD COLUMN case_slip_cover BOOLEAN,
    ADD COLUMN other_features TEXT;

-- ----------------------------
-- Film-level junction tables
-- ----------------------------

-- Genres
CREATE TABLE film_genre (
    film_id     BIGINT NOT NULL REFERENCES film(id) ON DELETE CASCADE,
    genre       TEXT NOT NULL,
    
    PRIMARY KEY (film_id, genre)
);

CREATE INDEX idx_film_genre_film_id ON film_genre(film_id);
CREATE INDEX idx_film_genre_genre ON film_genre(genre);

-- Studios
CREATE TABLE film_studio (
    film_id     BIGINT NOT NULL REFERENCES film(id) ON DELETE CASCADE,
    studio      TEXT NOT NULL,
    
    PRIMARY KEY (film_id, studio)
);

CREATE INDEX idx_film_studio_film_id ON film_studio(film_id);
CREATE INDEX idx_film_studio_studio ON film_studio(studio);

-- Countries of origin
CREATE TABLE film_country (
    film_id     BIGINT NOT NULL REFERENCES film(id) ON DELETE CASCADE,
    country     TEXT NOT NULL,
    sequence    INTEGER NOT NULL,  -- 1, 2, or 3 for CountryOfOrigin1/2/3
    
    PRIMARY KEY (film_id, sequence),
    CHECK (sequence IN (1, 2, 3))
);

CREATE INDEX idx_film_country_film_id ON film_country(film_id);

-- Crew (Directors and Producers)
CREATE TABLE film_crew (
    id          BIGSERIAL PRIMARY KEY,
    film_id     BIGINT NOT NULL REFERENCES film(id) ON DELETE CASCADE,
    first_name  TEXT,
    middle_name TEXT,
    last_name   TEXT,
    birth_year  INTEGER,
    role_type   TEXT NOT NULL,  -- 'Director', 'Producer', 'Executive Producer'
    credited_as TEXT
);

CREATE INDEX idx_film_crew_film_id ON film_crew(film_id);
CREATE INDEX idx_film_crew_role_type ON film_crew(role_type);
CREATE INDEX idx_film_crew_last_name ON film_crew(last_name);

-- ----------------------------
-- Edition-level tables
-- ----------------------------

-- Subtitles
CREATE TABLE edition_subtitle (
    edition_id  BIGINT NOT NULL REFERENCES edition(id) ON DELETE CASCADE,
    language    TEXT NOT NULL,
    
    PRIMARY KEY (edition_id, language)
);

CREATE INDEX idx_edition_subtitle_edition_id ON edition_subtitle(edition_id);

-- Feature lookup table (seeded with known DVD Profiler features)
CREATE TABLE feature (
    id          BIGSERIAL PRIMARY KEY,
    name        TEXT NOT NULL UNIQUE,  -- e.g., 'FeatureCommentary', 'FeatureTrailer'
    display_name TEXT  -- human-readable, e.g., 'Commentary', 'Trailers'
);

-- Seed feature lookup table with known DVD Profiler feature names
INSERT INTO feature (name, display_name) VALUES
    ('FeatureSceneAccess', 'Scene Access'),
    ('FeatureCommentary', 'Commentary'),
    ('FeatureTrailer', 'Trailers'),
    ('FeaturePhotoGallery', 'Photo Gallery'),
    ('FeatureDeletedScenes', 'Deleted Scenes'),
    ('FeatureMakingOf', 'Making Of'),
    ('FeatureProductionNotes', 'Production Notes'),
    ('FeatureGame', 'Game'),
    ('FeatureDVDROMContent', 'DVD-ROM Content'),
    ('FeatureMultiAngle', 'Multi-Angle'),
    ('FeatureMusicVideos', 'Music Videos'),
    ('FeatureInterviews', 'Interviews'),
    ('FeatureStoryboardComparisons', 'Storyboard Comparisons'),
    ('FeatureOuttakes', 'Outtakes'),
    ('FeatureClosedCaptioned', 'Closed Captioned'),
    ('FeatureTHXCertified', 'THX Certified'),
    ('FeaturePIP', 'Picture-in-Picture'),
    ('FeatureBDLive', 'BD-Live'),
    ('FeatureBonusTrailers', 'Bonus Trailers'),
    ('FeatureDigitalCopy', 'Digital Copy'),
    ('FeatureDBOX', 'DBOX'),
    ('FeatureCineChat', 'CineChat'),
    ('FeaturePlayAll', 'Play All'),
    ('FeatureMovieIQ', 'MovieIQ')
ON CONFLICT (name) DO NOTHING;

-- Edition features (junction table)
CREATE TABLE edition_feature (
    edition_id  BIGINT NOT NULL REFERENCES edition(id) ON DELETE CASCADE,
    feature_id  BIGINT NOT NULL REFERENCES feature(id) ON DELETE CASCADE,
    
    PRIMARY KEY (edition_id, feature_id)
);

CREATE INDEX idx_edition_feature_edition_id ON edition_feature(edition_id);
CREATE INDEX idx_edition_feature_feature_id ON edition_feature(feature_id);
