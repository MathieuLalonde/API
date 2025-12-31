ALTER TABLE edition
ADD COLUMN external_id TEXT,
ADD COLUMN external_id_base TEXT,
ADD COLUMN external_id_type TEXT,
ADD COLUMN external_locality_id INTEGER,
ADD COLUMN external_variant_num INTEGER,
ADD COLUMN last_edited_at TIMESTAMPTZ;

ALTER TABLE edition
ALTER COLUMN external_id SET NOT NULL;

CREATE UNIQUE INDEX idx_edition_external_id
    ON edition (external_id);

ALTER TABLE edition
ADD COLUMN previous_external_id TEXT;
