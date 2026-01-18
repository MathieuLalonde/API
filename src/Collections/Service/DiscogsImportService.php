<?php

declare(strict_types=1);

namespace App\Collections\Service;

use App\Collections\DTO\ImportResult;
use App\Collections\Infrastructure\Repository\DiscogsImportRepository;
use RuntimeException;

/**
 * Service for importing Discogs LP collection data.
 * Fetches from Discogs API and orchestrates the import process.
 */
class DiscogsImportService
{
    private const DISCOGS_API_BASE = 'https://api.discogs.com';
    private const USER_AGENT = 'MyDVDCollection/1.0 +https://mathieulalonde.com';

    public function __construct(
        private DiscogsImportRepository $repository
    ) {}

    /**
     * Import collection from Discogs API.
     *
     * @param string $username Discogs username
     * @return ImportResult Import results
     */
    public function importFromDiscogs(string $username): ImportResult
    {
        $importedCount = 0;
        $errors = [];
        $warnings = [];
        $importedInstanceIds = [];

        try {
            // Load release cache for change detection
            $cacheStartTime = microtime(true);
            $releaseCache = $this->repository->loadReleaseCache();
            $cacheLoadTime = microtime(true) - $cacheStartTime;
            echo "Loaded " . count($releaseCache) . " existing releases into cache (" . round($cacheLoadTime * 1000, 1) . "ms)\n";

            $skippedCount = 0;
            $newCount = 0;
            $updatedCount = 0;

            // Fetch all pages from Discogs API
            echo "Fetching collection from Discogs API...\n";
            $page = 1;
            $perPage = 50;
            $allReleases = [];

            do {
                $url = sprintf(
                    '%s/users/%s/collection/folders/0/releases?page=%d&per_page=%d&sort=label&sort_order=asc',
                    self::DISCOGS_API_BASE,
                    urlencode($username),
                    $page,
                    $perPage
                );

                echo "Fetching page $page...\n";
                $response = $this->fetchApiPage($url);
                
                if (!isset($response['releases']) || !is_array($response['releases'])) {
                    throw new RuntimeException("Invalid API response: missing 'releases' array");
                }

                $allReleases = array_merge($allReleases, $response['releases']);

                $pagination = $response['pagination'] ?? [];
                $pages = $pagination['pages'] ?? 1;
                $page++;

            } while ($page <= ($pages ?? 1));

            echo "Fetched " . count($allReleases) . " releases from API\n";
            echo "Processing releases...\n";

            // Process each release
            foreach ($allReleases as $releaseData) {
                try {
                    $instanceId = (int)$releaseData['instance_id'];
                    $basicInfo = $releaseData['basic_information'] ?? [];
                    
                    if (empty($basicInfo)) {
                        $errors[] = "Error importing release instance {$instanceId}: Missing basic_information";
                        continue;
                    }

                    $importedInstanceIds[] = $instanceId;

                    // Check if release exists and hasn't changed
                    $existingRelease = $releaseCache[$instanceId] ?? null;
                    if ($existingRelease !== null) {
                        // Release exists, check if we need to update
                        // For now, always update if it exists (instance_id is the unique key)
                        $updatedCount++;
                    } else {
                        $newCount++;
                        $title = $basicInfo['title'] ?? 'Unknown';
                        echo "NEW: {$instanceId} - {$title}\n";
                    }

                    // Get or create album by master_id
                    $masterId = isset($basicInfo['master_id']) && $basicInfo['master_id'] ? (int)$basicInfo['master_id'] : null;
                    $albumId = $this->importAlbum($basicInfo, $masterId);

                    // Import release
                    $releaseId = $this->importRelease($releaseData, $albumId);

                    // Import relationships
                    $this->importReleaseRelationships($releaseId, $basicInfo);

                    $importedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Error importing release: " . $e->getMessage();
                }
            }

            // Cleanup orphaned releases and albums
            if (!empty($importedInstanceIds)) {
                $this->repository->deleteOrphanedReleases($importedInstanceIds);
            }
            $this->repository->deleteOrphanedAlbums();

            // Summary statistics
            echo "\nImport summary:\n";
            echo "  Total processed: {$importedCount}\n";
            echo "  New releases: {$newCount}\n";
            echo "  Updated releases: {$updatedCount}\n";
            echo "  Skipped (unchanged): {$skippedCount}\n";

            $success = empty($errors);
            return new ImportResult($success, $importedCount, $errors, $warnings);
        } catch (\Exception $e) {
            return new ImportResult(false, $importedCount, [$e->getMessage()], $warnings);
        }
    }

    /**
     * Fetch a single page from Discogs API.
     *
     * @param string $url API URL
     * @return array<string, mixed> Decoded JSON response
     */
    private function fetchApiPage(string $url): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException("Failed to initialize cURL");
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false || !empty($curlError)) {
            throw new RuntimeException("cURL error: " . $curlError);
        }

        if ($httpCode !== 200) {
            throw new RuntimeException("HTTP error {$httpCode}: " . substr($response, 0, 200));
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("JSON decode error: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Import album from basic_information.
     *
     * @param array<string, mixed> $basicInfo Basic information from API
     * @param int|null $masterId Master ID
     * @return int Album ID
     */
    private function importAlbum(array $basicInfo, ?int $masterId): int
    {
        $title = $basicInfo['title'] ?? 'Unknown';
        $year = isset($basicInfo['year']) && $basicInfo['year'] ? (int)$basicInfo['year'] : null;
        $thumbUrl = $basicInfo['thumb'] ?? null;
        $coverImageUrl = $basicInfo['cover_image'] ?? null;

        // Normalized title (for future search/filtering if needed)
        $normalizedTitle = mb_strtolower(trim($title));

        // Check if album exists by master_id
        $existingAlbum = null;
        if ($masterId !== null) {
            $existingAlbum = $this->repository->findAlbumByMasterId($masterId);
        }

        if ($existingAlbum) {
            // Update existing album
            $albumId = (int)$existingAlbum['id'];
            $this->repository->updateAlbum($albumId, [
                'title' => $title,
                'normalized_title' => $normalizedTitle,
                'year' => $year,
                'thumb_url' => $thumbUrl,
                'cover_image_url' => $coverImageUrl,
            ]);
        } else {
            // Create new album
            $albumId = $this->repository->createAlbum([
                'master_id' => $masterId,
                'title' => $title,
                'normalized_title' => $normalizedTitle,
                'year' => $year,
                'thumb_url' => $thumbUrl,
                'cover_image_url' => $coverImageUrl,
            ]);
        }

        return $albumId;
    }

    /**
     * Import release from release data.
     *
     * @param array<string, mixed> $releaseData Full release data from API
     * @param int $albumId Album ID
     * @return int Release ID
     */
    private function importRelease(array $releaseData, int $albumId): int
    {
        $instanceId = (int)$releaseData['instance_id'];
        $basicInfo = $releaseData['basic_information'] ?? [];
        $discogsId = (int)($basicInfo['id'] ?? 0);
        $rating = isset($releaseData['rating']) && $releaseData['rating'] ? (int)$releaseData['rating'] : null;
        $resourceUrl = $basicInfo['resource_url'] ?? null;

        // Parse date_added
        $dateAdded = null;
        if (isset($releaseData['date_added']) && $releaseData['date_added']) {
            $timestamp = strtotime($releaseData['date_added']);
            if ($timestamp !== false) {
                $dateAdded = date('c', $timestamp);
            }
        }

        // Parse notes for condition
        $mediaCondition = null;
        $sleeveCondition = null;
        if (isset($releaseData['notes']) && is_array($releaseData['notes'])) {
            foreach ($releaseData['notes'] as $note) {
                $fieldId = $note['field_id'] ?? null;
                $value = $note['value'] ?? null;
                
                if ($fieldId === 1) {
                    $mediaCondition = $value;
                } elseif ($fieldId === 2) {
                    $sleeveCondition = $value;
                }
            }
        }

        return $this->repository->upsertRelease([
            'album_id' => $albumId,
            'discogs_id' => $discogsId,
            'discogs_instance_id' => $instanceId,
            'date_added' => $dateAdded,
            'rating' => $rating,
            'media_condition' => $mediaCondition,
            'sleeve_condition' => $sleeveCondition,
            'resource_url' => $resourceUrl,
        ]);
    }

    /**
     * Import release relationships (artists, labels, genres, styles, formats).
     *
     * @param int $releaseId Release ID
     * @param array<string, mixed> $basicInfo Basic information from API
     */
    private function importReleaseRelationships(int $releaseId, array $basicInfo): void
    {
        // Artists
        $artists = [];
        if (isset($basicInfo['artists']) && is_array($basicInfo['artists'])) {
            foreach ($basicInfo['artists'] as $artist) {
                $artistName = $artist['name'] ?? null;
                if ($artistName) {
                    $artists[] = $artistName;
                }
            }
        }
        $this->repository->syncReleaseArtists($releaseId, $artists);

        // Labels
        $labels = [];
        if (isset($basicInfo['labels']) && is_array($basicInfo['labels'])) {
            foreach ($basicInfo['labels'] as $label) {
                $labels[] = [
                    'name' => $label['name'] ?? 'Unknown',
                    'catalog_no' => $label['catno'] ?? null,
                ];
            }
        }
        $this->repository->syncReleaseLabels($releaseId, $labels);

        // Genres
        $genres = [];
        if (isset($basicInfo['genres']) && is_array($basicInfo['genres'])) {
            $genres = $basicInfo['genres'];
        }
        $this->repository->syncReleaseGenres($releaseId, $genres);

        // Styles
        $styles = [];
        if (isset($basicInfo['styles']) && is_array($basicInfo['styles'])) {
            $styles = $basicInfo['styles'];
        }
        $this->repository->syncReleaseStyles($releaseId, $styles);

        // Formats
        $formats = [];
        if (isset($basicInfo['formats']) && is_array($basicInfo['formats'])) {
            foreach ($basicInfo['formats'] as $format) {
                $formatName = $format['name'] ?? 'Unknown';
                $quantity = $format['qty'] ?? null;
                $descriptions = isset($format['descriptions']) && is_array($format['descriptions'])
                    ? implode(', ', $format['descriptions'])
                    : null;

                $formats[] = [
                    'name' => $formatName,
                    'quantity' => $quantity,
                    'descriptions' => $descriptions,
                ];
            }
        }
        $this->repository->syncReleaseFormats($releaseId, $formats);
    }
}
