SET search_path TO collections;

-- Create junction table for multiple media types per edition
CREATE TABLE edition_media_type (
    edition_id      BIGINT NOT NULL REFERENCES edition(id) ON DELETE CASCADE,
    media_type      TEXT NOT NULL,  -- 'DVD' | 'BLURAY' | 'UHD'
    
    PRIMARY KEY (edition_id, media_type)
);

-- Create index for efficient lookups
CREATE INDEX idx_edition_media_type_edition_id ON edition_media_type(edition_id);
CREATE INDEX idx_edition_media_type_type ON edition_media_type(media_type);
