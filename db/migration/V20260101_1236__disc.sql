SET search_path TO collections;

CREATE TABLE edition_disc (
    id              BIGSERIAL PRIMARY KEY,
    edition_id      BIGINT NOT NULL REFERENCES edition(id) ON DELETE CASCADE,

    disc_number     INTEGER,        -- 1, 2, 3…
    role            TEXT,           -- "Main Feature", "Bonus Disc"
    label           TEXT,           -- printed label, if known
    notes           TEXT            -- freeform
);

-- Child cleanup
CREATE INDEX idx_disc_edition_id
    ON edition_disc (edition_id);

CREATE TABLE disc (
    id              BIGSERIAL PRIMARY KEY,

    fingerprint     TEXT,           -- DVD/BD disc ID, optional
    dual_layered    BOOLEAN,
    dual_sided      BOOLEAN,

    created_at      TIMESTAMP DEFAULT now()
);

ALTER TABLE edition_disc
    ADD COLUMN disc_id BIGINT
    REFERENCES disc(id);