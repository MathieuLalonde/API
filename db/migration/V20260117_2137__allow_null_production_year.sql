SET search_path TO collections;

-- Allow production_year to be null (some DVDs like TV shows may not have a production year)
ALTER TABLE collections.film
    ALTER COLUMN production_year DROP NOT NULL;

-- Update the unique index to handle null production_year
-- PostgreSQL unique indexes treat NULL values as distinct, so multiple films with same title
-- but NULL year will be allowed (which is fine for TV shows or unknown-year films)
DROP INDEX IF EXISTS collections.ux_film_identity;
CREATE UNIQUE INDEX ux_film_identity
ON collections.film (normalized_title, COALESCE(production_year, -1));
