<?php
declare(strict_types=1);

namespace App\Collections\Infrastructure\Repository;

use App\Collections\Infrastructure\Import\TitleNormalizer;
use PDO;
use PDOStatement;

/**
 * Repository for DVD Profiler import operations.
 * Manages all database operations including prepared statements for importing films, editions, and relationships.
 */
class ImportRepository
{
    private PDOStatement $findFilmStmt;
    private PDOStatement $findFilmsByYearStmt;
    private PDOStatement $insertFilmStmt;
    private PDOStatement $updateFilmRatingStmt;
    private PDOStatement $updateFilmStmt;
    private PDOStatement $getFilmByIdStmt;
    private PDOStatement $editionUpsertStmt;
    private PDOStatement $deleteEditionDiscStmt;
    private PDOStatement $insertEditionDiscStmt;
    private PDOStatement $deleteAudioStmt;
    private PDOStatement $insertAudioStmt;
    private PDOStatement $upsertVideoStmt;
    private PDOStatement $deleteFilmGenreStmt;
    private PDOStatement $insertFilmGenreStmt;
    private PDOStatement $deleteFilmStudioStmt;
    private PDOStatement $insertFilmStudioStmt;
    private PDOStatement $deleteFilmCountryStmt;
    private PDOStatement $insertFilmCountryStmt;
    private PDOStatement $deleteFilmCrewStmt;
    private PDOStatement $insertFilmCrewStmt;
    private PDOStatement $deleteEditionRegionStmt;
    private PDOStatement $insertEditionRegionStmt;
    private PDOStatement $deleteEditionMediaTypeStmt;
    private PDOStatement $insertEditionMediaTypeStmt;
    private PDOStatement $deleteEditionSubtitleStmt;
    private PDOStatement $insertEditionSubtitleStmt;
    private PDOStatement $insertEditionFeatureStmt;
    private PDOStatement $deleteEditionFeatureStmt;
    private array $featureCache = [];

    public function __construct(private PDO $pdo)
    {
        // Set search_path to collections schema
        $pdo->exec("SET search_path TO collections");
        $pdo->exec("SET client_encoding TO 'UTF8'");

        // Initialize prepared statements
        // Note: findFilmStmt kept for backward compatibility but will be replaced with PHP-side matching
        $this->findFilmStmt = $pdo->prepare("
            SELECT id
            FROM film
            WHERE production_year = :year
              AND normalized_title = ANY(:candidates)
            LIMIT 1
        ");

        $this->findFilmsByYearStmt = $pdo->prepare("
            SELECT id, title, sort_title, original_title, normalized_title, 
                   production_year, running_time_min, rating_system, rating, rating_age, rating_details
            FROM film
            WHERE production_year = :year
        ");

        $this->getFilmByIdStmt = $pdo->prepare("
            SELECT id, title, sort_title, original_title, normalized_title,
                   production_year, running_time_min, rating_system, rating, rating_age, rating_details
            FROM film
            WHERE id = :film_id
        ");

        $this->insertFilmStmt = $pdo->prepare("
            INSERT INTO film (
                title,
                sort_title,
                original_title,
                normalized_title,
                production_year,
                running_time_min,
                rating_system,
                rating,
                rating_age,
                rating_details
            )
            VALUES (
                :title,
                :sort_title,
                :original_title,
                :normalized_title,
                :year,
                :runtime,
                :rating_system,
                :rating,
                :rating_age,
                :rating_details
            )
            RETURNING id
        ");

        $this->updateFilmRatingStmt = $pdo->prepare("
            UPDATE film
            SET rating_system = :rating_system,
                rating = :rating,
                rating_age = :rating_age,
                rating_details = :rating_details
            WHERE id = :film_id
        ");

        $this->updateFilmStmt = $pdo->prepare("
            UPDATE film
            SET title = :title,
                sort_title = :sort_title,
                original_title = :original_title,
                normalized_title = :normalized_title,
                running_time_min = :running_time_min,
                rating_system = :rating_system,
                rating = :rating,
                rating_age = :rating_age,
                rating_details = :rating_details
            WHERE id = :film_id
        ");

        $this->editionUpsertStmt = $pdo->prepare("
            INSERT INTO edition (
                film_id,
                external_id,
                external_id_base,
                external_id_type,
                external_locality_id,
                external_variant_num,
                upc,
                release_date,
                name,
                distributor,
                last_edited_at,
                case_type,
                case_slip_cover,
                other_features
            )
            VALUES (
                :film_id,
                :external_id,
                :external_id_base,
                :external_id_type,
                :external_locality_id,
                :external_variant_num,
                :upc,
                :release_date,
                :name,
                :distributor,
                :last_edited_at,
                :case_type,
                :case_slip_cover,
                :other_features
            )
            ON CONFLICT (external_id)
            DO UPDATE SET
                upc = EXCLUDED.upc,
                release_date = EXCLUDED.release_date,
                name = EXCLUDED.name,
                distributor = EXCLUDED.distributor,
                last_edited_at = EXCLUDED.last_edited_at,
                case_type = EXCLUDED.case_type,
                case_slip_cover = EXCLUDED.case_slip_cover,
                other_features = EXCLUDED.other_features
            RETURNING id
        ");

        $this->deleteEditionDiscStmt = $pdo->prepare("DELETE FROM edition_disc WHERE edition_id = ?");
        $this->insertEditionDiscStmt = $pdo->prepare("
            INSERT INTO edition_disc (
                edition_id,
                disc_number,
                role,
                label,
                notes
            ) VALUES (
                :edition_id,
                :disc_number,
                :role,
                :label,
                :notes
            )
        ");

        $this->deleteAudioStmt = $pdo->prepare("DELETE FROM audio_track WHERE edition_id = ?");
        $this->insertAudioStmt = $pdo->prepare("
            INSERT INTO audio_track (
                edition_id,
                language,
                channel_layout,
                format,
                is_descriptive
            ) VALUES (
                :edition_id,
                :language,
                :channel_layout,
                :format,
                :is_descriptive
            )
        ");

        $this->upsertVideoStmt = $pdo->prepare("
            INSERT INTO video_format (
                edition_id,
                is_color,
                is_black_and_white,
                is_colorized,
                is_mixed_color,
                is_2d,
                is_3d_anaglyph,
                is_3d_bluray,
                is_16x9,
                aspect_ratio,
                is_full_frame,
                is_letterbox,
                is_pan_and_scan,
                is_dual_layered,
                is_dual_sided,
                video_standard
            ) VALUES (
                :edition_id,
                :is_color,
                :is_black_and_white,
                :is_colorized,
                :is_mixed_color,
                :is_2d,
                :is_3d_anaglyph,
                :is_3d_bluray,
                :is_16x9,
                :aspect_ratio,
                :is_full_frame,
                :is_letterbox,
                :is_pan_and_scan,
                :is_dual_layered,
                :is_dual_sided,
                :video_standard
            )
            ON CONFLICT (edition_id)
            DO UPDATE SET
                is_color = EXCLUDED.is_color,
                is_black_and_white = EXCLUDED.is_black_and_white,
                is_colorized = EXCLUDED.is_colorized,
                is_mixed_color = EXCLUDED.is_mixed_color,
                is_2d = EXCLUDED.is_2d,
                is_3d_anaglyph = EXCLUDED.is_3d_anaglyph,
                is_3d_bluray = EXCLUDED.is_3d_bluray,
                is_16x9 = EXCLUDED.is_16x9,
                aspect_ratio = EXCLUDED.aspect_ratio,
                is_full_frame = EXCLUDED.is_full_frame,
                is_letterbox = EXCLUDED.is_letterbox,
                is_pan_and_scan = EXCLUDED.is_pan_and_scan,
                is_dual_layered = EXCLUDED.is_dual_layered,
                is_dual_sided = EXCLUDED.is_dual_sided,
                video_standard = EXCLUDED.video_standard
        ");

        // Film-level junction tables
        $this->deleteFilmGenreStmt = $pdo->prepare("DELETE FROM film_genre WHERE film_id = ?");
        $this->insertFilmGenreStmt = $pdo->prepare("INSERT INTO film_genre (film_id, genre) VALUES (?, ?) ON CONFLICT DO NOTHING");

        $this->deleteFilmStudioStmt = $pdo->prepare("DELETE FROM film_studio WHERE film_id = ?");
        $this->insertFilmStudioStmt = $pdo->prepare("INSERT INTO film_studio (film_id, studio) VALUES (?, ?) ON CONFLICT DO NOTHING");

        $this->deleteFilmCountryStmt = $pdo->prepare("DELETE FROM film_country WHERE film_id = ?");
        $this->insertFilmCountryStmt = $pdo->prepare("INSERT INTO film_country (film_id, country, sequence) VALUES (?, ?, ?) ON CONFLICT DO NOTHING");

        $this->deleteFilmCrewStmt = $pdo->prepare("DELETE FROM film_crew WHERE film_id = ?");
        $this->insertFilmCrewStmt = $pdo->prepare("
            INSERT INTO film_crew (film_id, first_name, middle_name, last_name, birth_year, role_type, credited_as)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        // Edition-level junction tables
        $this->deleteEditionRegionStmt = $pdo->prepare("DELETE FROM edition_region WHERE edition_id = ?");
        $this->insertEditionRegionStmt = $pdo->prepare("INSERT INTO edition_region (edition_id, region_code) VALUES (?, ?) ON CONFLICT DO NOTHING");

        $this->deleteEditionMediaTypeStmt = $pdo->prepare("DELETE FROM edition_media_type WHERE edition_id = ?");
        $this->insertEditionMediaTypeStmt = $pdo->prepare("INSERT INTO edition_media_type (edition_id, media_type) VALUES (?, ?) ON CONFLICT DO NOTHING");

        $this->deleteEditionSubtitleStmt = $pdo->prepare("DELETE FROM edition_subtitle WHERE edition_id = ?");
        $this->insertEditionSubtitleStmt = $pdo->prepare("INSERT INTO edition_subtitle (edition_id, language) VALUES (?, ?) ON CONFLICT DO NOTHING");

        $this->insertEditionFeatureStmt = $pdo->prepare("INSERT INTO edition_feature (edition_id, feature_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
        $this->deleteEditionFeatureStmt = $pdo->prepare("DELETE FROM edition_feature WHERE edition_id = ?");
        
        // Load feature cache once at initialization
        $this->loadFeatureCache();
    }

    /**
     * Find a film by year and normalized title candidates.
     * Compares candidates against all three title fields (title, original_title, sort_title).
     *
     * @param int|null $year Production year (can be null for TV shows)
     * @param array<string> $candidates Normalized title candidates
     * @param string|null $incomingTitleNorm Normalized main title (first candidate); used to avoid merging different volumes
     * @return int|null Film ID or null if not found
     */
    public function findFilmByTitleAndYear(?int $year, array $candidates, ?string $incomingTitleNorm = null): ?int
    {
        if (empty($candidates) || $year === null) {
            return null;
        }

        // Load all films for this year
        $this->findFilmsByYearStmt->execute(['year' => $year]);
        $films = $this->findFilmsByYearStmt->fetchAll(PDO::FETCH_ASSOC);

        // Compare candidates against all three title fields for each film
        foreach ($films as $film) {
            $filmTitleNorm = $film['normalized_title'];
            $filmOrigTitleNorm = TitleNormalizer::normalize($film['original_title']);
            $filmSortTitleNorm = TitleNormalizer::normalize($film['sort_title']);

            // Check if any candidate matches any of the film's normalized title fields
            foreach ($candidates as $candidate) {
                if ($candidate === $filmTitleNorm ||
                    ($filmOrigTitleNorm !== null && $candidate === $filmOrigTitleNorm) ||
                    ($filmSortTitleNorm !== null && $candidate === $filmSortTitleNorm)) {
                    // Don't merge different volumes (e.g. "Volume One" vs "Volume Two: The War Years")
                    if ($incomingTitleNorm !== null && !TitleNormalizer::sameVolumeOrNoVolume($incomingTitleNorm, $filmTitleNorm)) {
                        continue;
                    }
                    return (int)$film['id'];
                }
            }
        }

        return null;
    }

    /**
     * Create a new film.
     *
     * @param array<string|int|null> $data Film data
     * @return int Created film ID
     */
    public function createFilm(array $data): int
    {
        $this->insertFilmStmt->execute($data);
        return (int)$this->insertFilmStmt->fetchColumn();
    }

    /**
     * Get film by ID.
     *
     * @param int $filmId Film ID
     * @return array<string|int|null>|null Film data or null if not found
     */
    public function getFilmById(int $filmId): ?array
    {
        $this->getFilmByIdStmt->execute(['film_id' => $filmId]);
        $result = $this->getFilmByIdStmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Update film rating information.
     *
     * @param int $filmId Film ID
     * @param array<string|int|null> $ratingData Rating data
     */
    public function updateFilmRating(int $filmId, array $ratingData): void
    {
        $this->updateFilmRatingStmt->execute([
            'film_id' => $filmId,
            ...$ratingData,
        ]);
    }

    /**
     * Update all film fields.
     *
     * @param int $filmId Film ID
     * @param array<string|int|null> $data Film data
     */
    public function updateFilm(int $filmId, array $data): void
    {
        $this->updateFilmStmt->execute([
            'film_id' => $filmId,
            ...$data,
        ]);
    }

    /**
     * Upsert an edition (insert or update).
     *
     * @param array<string|int|null> $data Edition data
     * @return int Edition ID
     */
    public function upsertEdition(array $data): int
    {
        $this->editionUpsertStmt->execute($data);
        return (int)$this->editionUpsertStmt->fetchColumn();
    }

    /**
     * Sync film genres (delete existing, insert new).
     *
     * @param int $filmId Film ID
     * @param array<string> $genres Genre names
     */
    public function syncFilmGenres(int $filmId, array $genres): void
    {
        $this->deleteFilmGenreStmt->execute([$filmId]);
        
        // Filter and trim genres
        $validGenres = array_filter(array_map('trim', $genres));
        if (empty($validGenres)) {
            return;
        }
        
        // Batch insert
        $values = implode(',', array_fill(0, count($validGenres), '(?, ?)'));
        $stmt = $this->pdo->prepare("
            INSERT INTO film_genre (film_id, genre) 
            VALUES {$values} 
            ON CONFLICT DO NOTHING
        ");
        
        $params = [];
        foreach ($validGenres as $genre) {
            $params[] = $filmId;
            $params[] = $genre;
        }
        $stmt->execute($params);
    }

    /**
     * Sync film studios (delete existing, insert new).
     *
     * @param int $filmId Film ID
     * @param array<string> $studios Studio names
     */
    public function syncFilmStudios(int $filmId, array $studios): void
    {
        $this->deleteFilmStudioStmt->execute([$filmId]);
        
        // Filter and trim studios
        $validStudios = array_filter(array_map('trim', $studios));
        if (empty($validStudios)) {
            return;
        }
        
        // Batch insert
        $values = implode(',', array_fill(0, count($validStudios), '(?, ?)'));
        $stmt = $this->pdo->prepare("
            INSERT INTO film_studio (film_id, studio) 
            VALUES {$values} 
            ON CONFLICT DO NOTHING
        ");
        
        $params = [];
        foreach ($validStudios as $studio) {
            $params[] = $filmId;
            $params[] = $studio;
        }
        $stmt->execute($params);
    }

    /**
     * Sync film countries (delete existing, insert new).
     *
     * @param int $filmId Film ID
     * @param array<int, string> $countries Map of sequence (1-3) to country name
     */
    public function syncFilmCountries(int $filmId, array $countries): void
    {
        $this->deleteFilmCountryStmt->execute([$filmId]);
        
        // Filter and trim countries
        $validCountries = [];
        foreach ($countries as $sequence => $country) {
            $countryName = trim($country);
            if ($countryName) {
                $validCountries[$sequence] = $countryName;
            }
        }
        
        if (empty($validCountries)) {
            return;
        }
        
        // Batch insert
        $values = implode(',', array_fill(0, count($validCountries), '(?, ?, ?)'));
        $stmt = $this->pdo->prepare("
            INSERT INTO film_country (film_id, country, sequence) 
            VALUES {$values} 
            ON CONFLICT DO NOTHING
        ");
        
        $params = [];
        foreach ($validCountries as $sequence => $country) {
            $params[] = $filmId;
            $params[] = $country;
            $params[] = $sequence;
        }
        $stmt->execute($params);
    }

    /**
     * Sync film crew (directors and producers) (delete existing, insert new).
     *
     * @param int $filmId Film ID
     * @param array<array{first_name?: string, middle_name?: string, last_name?: string, birth_year?: int, role_type: string, credited_as?: string}> $crew Crew members
     */
    public function syncFilmCrew(int $filmId, array $crew): void
    {
        $this->deleteFilmCrewStmt->execute([$filmId]);
        
        if (empty($crew)) {
            return;
        }
        
        // Batch insert
        $values = implode(',', array_fill(0, count($crew), '(?, ?, ?, ?, ?, ?, ?)'));
        $stmt = $this->pdo->prepare("
            INSERT INTO film_crew (film_id, first_name, middle_name, last_name, birth_year, role_type, credited_as) 
            VALUES {$values}
        ");
        
        $params = [];
        foreach ($crew as $member) {
            $params[] = $filmId;
            $params[] = $member['first_name'] ?? null;
            $params[] = $member['middle_name'] ?? null;
            $params[] = $member['last_name'] ?? null;
            $params[] = $member['birth_year'] ?? null;
            $params[] = $member['role_type'];
            $params[] = $member['credited_as'] ?? null;
        }
        $stmt->execute($params);
    }

    /**
     * Sync edition discs (delete existing, insert new).
     *
     * @param int $editionId Edition ID
     * @param array<array{ disc_number: int, role?: string, label?: string, notes?: string}> $discs Disc data
     */
    public function syncEditionDiscs(int $editionId, array $discs): void
    {
        $this->deleteEditionDiscStmt->execute([$editionId]);
        
        if (empty($discs)) {
            return;
        }
        
        // Batch insert
        $values = implode(',', array_fill(0, count($discs), '(?, ?, ?, ?, ?)'));
        $stmt = $this->pdo->prepare("
            INSERT INTO edition_disc (edition_id, disc_number, role, label, notes) 
            VALUES {$values}
        ");
        
        $params = [];
        foreach ($discs as $disc) {
            $params[] = $editionId;
            $params[] = $disc['disc_number'];
            $params[] = $disc['role'] ?? null;
            $params[] = $disc['label'] ?? null;
            $params[] = $disc['notes'] ?? null;
        }
        $stmt->execute($params);
    }

    /**
     * Sync audio tracks (delete existing, insert new).
     *
     * @param int $editionId Edition ID
     * @param array<array{language?: string, channel_layout?: string, format?: string, is_descriptive: int}> $tracks Audio track data
     */
    public function syncAudioTracks(int $editionId, array $tracks): void
    {
        $this->deleteAudioStmt->execute([$editionId]);
        
        if (empty($tracks)) {
            return;
        }
        
        // Batch insert
        $values = implode(',', array_fill(0, count($tracks), '(?, ?, ?, ?, ?)'));
        $stmt = $this->pdo->prepare("
            INSERT INTO audio_track (edition_id, language, channel_layout, format, is_descriptive) 
            VALUES {$values}
        ");
        
        $params = [];
        foreach ($tracks as $track) {
            $params[] = $editionId;
            $params[] = $track['language'] ?? null;
            $params[] = $track['channel_layout'] ?? null;
            $params[] = $track['format'] ?? null;
            $params[] = $track['is_descriptive'];
        }
        $stmt->execute($params);
    }

    /**
     * Upsert video format.
     *
     * @param array<string|int|float|null> $data Video format data
     */
    public function upsertVideoFormat(array $data): void
    {
        $this->upsertVideoStmt->execute($data);
    }

    /**
     * Sync edition regions (delete existing, insert new).
     *
     * @param int $editionId Edition ID
     * @param array<string> $regions Region codes
     */
    public function syncEditionRegions(int $editionId, array $regions): void
    {
        $this->deleteEditionRegionStmt->execute([$editionId]);
        
        // Filter and trim regions
        $validRegions = array_filter(array_map('trim', $regions));
        if (empty($validRegions)) {
            return;
        }
        
        // Batch insert
        $values = implode(',', array_fill(0, count($validRegions), '(?, ?)'));
        $stmt = $this->pdo->prepare("
            INSERT INTO edition_region (edition_id, region_code) 
            VALUES {$values} 
            ON CONFLICT DO NOTHING
        ");
        
        $params = [];
        foreach ($validRegions as $region) {
            $params[] = $editionId;
            $params[] = $region;
        }
        $stmt->execute($params);
    }

    /**
     * Sync edition media types (delete existing, insert new).
     *
     * @param int $editionId Edition ID
     * @param array<string> $mediaTypes Media types: 'DVD', 'BLURAY', 'UHD'
     */
    public function syncEditionMediaTypes(int $editionId, array $mediaTypes): void
    {
        $this->deleteEditionMediaTypeStmt->execute([$editionId]);
        
        // Filter and validate media types (only allow known values)
        $validTypes = ['DVD', 'BLURAY', 'UHD'];
        $filteredTypes = array_filter($mediaTypes, fn($type) => in_array($type, $validTypes, true));
        
        if (empty($filteredTypes)) {
            return;
        }
        
        // Batch insert
        $values = implode(',', array_fill(0, count($filteredTypes), '(?, ?)'));
        $stmt = $this->pdo->prepare("
            INSERT INTO edition_media_type (edition_id, media_type) 
            VALUES {$values} 
            ON CONFLICT DO NOTHING
        ");
        
        $params = [];
        foreach ($filteredTypes as $mediaType) {
            $params[] = $editionId;
            $params[] = $mediaType;
        }
        $stmt->execute($params);
    }

    /**
     * Sync edition subtitles (delete existing, insert new).
     *
     * @param int $editionId Edition ID
     * @param array<string> $languages Language names
     */
    public function syncEditionSubtitles(int $editionId, array $languages): void
    {
        $this->deleteEditionSubtitleStmt->execute([$editionId]);
        
        // Filter and trim languages
        $validLanguages = array_filter(array_map('trim', $languages));
        if (empty($validLanguages)) {
            return;
        }
        
        // Batch insert
        $values = implode(',', array_fill(0, count($validLanguages), '(?, ?)'));
        $stmt = $this->pdo->prepare("
            INSERT INTO edition_subtitle (edition_id, language) 
            VALUES {$values} 
            ON CONFLICT DO NOTHING
        ");
        
        $params = [];
        foreach ($validLanguages as $language) {
            $params[] = $editionId;
            $params[] = $language;
        }
        $stmt->execute($params);
    }

    /**
     * Load feature cache from database.
     * Called once during repository initialization.
     */
    private function loadFeatureCache(): void
    {
        $stmt = $this->pdo->query("SELECT id, name FROM feature");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->featureCache[$row['name']] = (int)$row['id'];
        }
    }

    /**
     * Sync edition features (delete existing, insert new).
     *
     * @param int $editionId Edition ID
     * @param array<string> $featureNames Feature names (e.g., 'FeatureCommentary')
     */
    public function syncEditionFeatures(int $editionId, array $featureNames): void
    {
        $this->deleteEditionFeatureStmt->execute([$editionId]);
        
        // Get valid feature IDs from cache
        $validFeatureIds = [];
        foreach ($featureNames as $featureName) {
            $featureId = $this->featureCache[$featureName] ?? null;
            if ($featureId) {
                $validFeatureIds[] = $featureId;
            }
        }
        
        if (empty($validFeatureIds)) {
            return;
        }
        
        // Batch insert
        $values = implode(',', array_fill(0, count($validFeatureIds), '(?, ?)'));
        $stmt = $this->pdo->prepare("
            INSERT INTO edition_feature (edition_id, feature_id) 
            VALUES {$values} 
            ON CONFLICT DO NOTHING
        ");
        
        $params = [];
        foreach ($validFeatureIds as $featureId) {
            $params[] = $editionId;
            $params[] = $featureId;
        }
        $stmt->execute($params);
    }

    /**
     * Load all editions into cache for fast lookup.
     * Returns array mapping external_id => ['upc' => ..., 'last_edited_at' => ...]
     *
     * @return array<string, array{upc: string|null, last_edited_at: string|null}>
     */
    public function loadEditionCache(): array
    {
        $stmt = $this->pdo->query("
            SELECT external_id, upc, last_edited_at
            FROM edition
        ");
        
        $cache = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cache[$row['external_id']] = [
                'upc' => $row['upc'],
                'last_edited_at' => $row['last_edited_at'],
            ];
        }
        
        return $cache;
    }

    /**
     * Find edition by external ID, returning UPC and last_edited_at for change detection.
     * DEPRECATED: Use loadEditionCache() and in-memory lookup instead for better performance.
     *
     * @param string $externalId External ID
     * @return array{id: int, upc: string|null, last_edited_at: string|null}|null Edition data or null if not found
     */
    public function findEditionByExternalId(string $externalId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, upc, last_edited_at
            FROM edition
            WHERE external_id = ?
            LIMIT 1
        ");
        $stmt->execute([$externalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row === false) {
            return null;
        }
        
        return [
            'id' => (int)$row['id'],
            'upc' => $row['upc'],
            'last_edited_at' => $row['last_edited_at'],
        ];
    }

    /**
     * Delete orphaned editions (editions not in the provided external IDs list).
     *
     * @param array<string> $importedExternalIds List of external IDs to keep
     */
    public function deleteOrphanedEditions(array $importedExternalIds): void
    {
        if (empty($importedExternalIds)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($importedExternalIds), '?'));
        $stmt = $this->pdo->prepare("
            DELETE FROM edition
            WHERE external_id NOT IN ($placeholders)
        ");
        $stmt->execute($importedExternalIds);
    }

    /**
     * Delete orphaned films (films with no editions).
     */
    public function deleteOrphanedFilms(): void
    {
        $this->pdo->exec("
            DELETE FROM film
            WHERE id NOT IN (
                SELECT DISTINCT film_id FROM edition
            )
        ");
    }
}
