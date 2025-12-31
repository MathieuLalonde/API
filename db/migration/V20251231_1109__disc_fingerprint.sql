CREATE TABLE disc_fingerprint (
    id              BIGSERIAL PRIMARY KEY,
    edition_id      BIGINT NOT NULL REFERENCES edition(id) ON DELETE CASCADE,

    disc_number     INTEGER NOT NULL,   -- 1, 2, 3...
    side            TEXT NOT NULL,      -- 'A' | 'B'

    disc_id         TEXT NOT NULL,      -- physical DiscID hash
    label           TEXT,
    is_dual_layered BOOLEAN
);

CREATE UNIQUE INDEX idx_disc_fingerprint_unique
    ON disc_fingerprint (edition_id, disc_number, side);
