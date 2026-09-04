<?php

declare(strict_types=1);

namespace App\Collections\Service;

use App\Collections\DTO\ImportResult;
use App\Collections\Infrastructure\Import\TitleNormalizer;
use App\Collections\Infrastructure\Parser\DvdProfilerXmlParser;
use App\Collections\Infrastructure\Repository\ImportRepository;
use RuntimeException;
use SimpleXMLElement;
use XMLReader;

/**
 * Service for importing DVD Profiler XML data.
 * Orchestrates the import process using parser and repository.
 */
class ImportService
{
    private const FEATURE_NAMES = [
        'FeatureSceneAccess',
        'FeatureCommentary',
        'FeatureTrailer',
        'FeaturePhotoGallery',
        'FeatureDeletedScenes',
        'FeatureMakingOf',
        'FeatureProductionNotes',
        'FeatureGame',
        'FeatureDVDROMContent',
        'FeatureMultiAngle',
        'FeatureMusicVideos',
        'FeatureInterviews',
        'FeatureStoryboardComparisons',
        'FeatureOuttakes',
        'FeatureClosedCaptioned',
        'FeatureTHXCertified',
        'FeaturePIP',
        'FeatureBDLive',
        'FeatureBonusTrailers',
        'FeatureDigitalCopy',
        'FeatureDBOX',
        'FeatureCineChat',
        'FeaturePlayAll',
        'FeatureMovieIQ',
    ];

    public function __construct(
        private DvdProfilerXmlParser $parser,
        private ImportRepository $repository
    ) {}

    /**
     * Import from an XML file path.
     * Uses streaming for large files to avoid memory issues.
     *
     * @param string $filePath Path to the XML file
     * @return ImportResult Import results
     */
    public function importFromXmlFile(string $filePath, bool $forceNonStreaming = false): ImportResult
    {
        $fileSize = filesize($filePath);
        $isLargeFile = $fileSize > 10 * 1024 * 1024; // > 10MB

        // Allow forcing non-streaming for testing/performance comparison
        if ($forceNonStreaming) {
            echo "Using non-streaming parser (full file load) for testing (" . round($fileSize / 1024 / 1024, 2) . " MB)...\n";
            $xml = $this->parser->loadFromFile($filePath);
            return $this->importFromXml($xml);
        }

        // For large files, skip direct load and use streaming immediately
        // Direct load would hang on large files, especially non-UTF-8 ones
        if ($isLargeFile) {
            echo "\nUsing streaming parser for large file (" . round($fileSize / 1024 / 1024, 2) . " MB)...\n";
            try {
                return $this->importFromXmlFileStreaming($filePath);
            } catch (\Exception $e) {
                error_log("Streaming failed, falling back to parser: " . $e->getMessage());
                $xml = $this->parser->loadFromFile($filePath);
                return $this->importFromXml($xml);
            }
        }

        // Small files: try direct load first (faster for small files)
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_file($filePath, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS);

        if ($xml !== false) {
            // Success! libxml handled the encoding
            libxml_clear_errors();
            return $this->importFromXml($xml);
        }

        // Direct load failed - try streaming approach
        libxml_clear_errors();
        try {
            return $this->importFromXmlFileStreaming($filePath);
        } catch (\Exception $e) {
            // Last resort: use parser's conversion method
            error_log("Streaming failed, falling back to parser: " . $e->getMessage());
            $xml = $this->parser->loadFromFile($filePath);
            return $this->importFromXml($xml);
        }
    }

    /**
     * Import from XML file using streaming parser.
     * Processes DVDs one at a time without loading entire file into memory.
     *
     * @param string $filePath Path to the XML file
     * @return ImportResult Import results
     */
    private function importFromXmlFileStreaming(string $filePath): ImportResult
    {
        libxml_use_internal_errors(true);

        // Ensure UTF-8 encoding
        $utf8File = $this->ensureUtf8File($filePath);
        $useTemp = $utf8File !== $filePath;
        if ($useTemp) {
            echo "Converted encoding to UTF-8...\n";
        }

        try {
            $reader = new XMLReader();
            if (!$reader->open($utf8File)) {
                throw new RuntimeException("Unable to open XML file: {$filePath}");
            }

            $importedCount = 0;
            $errors = [];
            $warnings = [];
            $importedExternalIds = [];
            $collectionOpen = false;

            // Load edition cache for fast skip checks
            $cacheStartTime = microtime(true);
            $editionCache = $this->repository->loadEditionCache();
            $cacheLoadTime = microtime(true) - $cacheStartTime;
            echo "Loaded " . count($editionCache) . " existing editions into cache (" . round($cacheLoadTime * 1000, 1) . "ms)\n";

            $skippedCount = 0;
            $newCount = 0;
            $updatedCount = 0;

            echo "Reading XML and processing DVDs (streaming)...\n";
            $collectionDepth = -1;

            // Progress tracking
            $startTime = microtime(true);
            $lastProgressTime = $startTime;
            $progressInterval = 5.0; // Show progress every 5 seconds

            while ($reader->read()) {
                // Skip until Collection root
                if (!$collectionOpen && $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'Collection') {
                    $collectionOpen = true;
                    $collectionDepth = $reader->depth;
                    continue;
                }

                // Process each DVD element that is a DIRECT child of Collection
                // This avoids matching <DVD>true</DVD> inside <MediaTypes>
                if ($collectionOpen && $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'DVD' && $reader->depth === $collectionDepth + 1) {
                    try {
                        // Read the DVD element as XML string
                        $dvdXml = $reader->readOuterXML();
                        if ($dvdXml) {
                            // readOuterXML() already returns <DVD>...</DVD>, just add XML declaration
                            $dvdXmlWrapped = '<?xml version="1.0" encoding="UTF-8"?>' . $dvdXml;
                            // Parse the DVD element directly
                            $dvd = simplexml_load_string($dvdXmlWrapped, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS);
                            if ($dvd !== false) {
                                $externalId = (string)$dvd->ID;
                                $title = isset($dvd->Title) ? trim((string)$dvd->Title) : null;

                                // Skip if no title
                                if (empty($title)) {
                                    $errors[] = "Error importing DVD {$externalId}: Missing title";
                                    continue;
                                }

                                $importedExternalIds[] = $externalId;

                                // Early skip check: if edition exists with same UPC and last_edited_at, skip processing
                                $upc = isset($dvd->UPC) ? (string)$dvd->UPC : null;
                                $upc = $upc === '' ? null : $upc;

                                $lastEditedAt = null;
                                if (isset($dvd->LastEdited)) {
                                    $lastEditedStr = trim((string)$dvd->LastEdited);
                                    if ($lastEditedStr) {
                                        $timestamp = strtotime($lastEditedStr);
                                        if ($timestamp !== false) {
                                            $lastEditedAt = date('c', $timestamp);
                                        }
                                    }
                                }

                                // Use in-memory cache instead of database query
                                $existingEdition = isset($editionCache[$externalId]) ? $editionCache[$externalId] : null;

                                if ($existingEdition !== null) {
                                    // Compare UPC (both must match exactly, including null)
                                    $upcMatch = ($existingEdition['upc'] === $upc) ||
                                        ($existingEdition['upc'] === null && $upc === null);

                                    // Compare last_edited_at (normalize both to timestamps for comparison)
                                    $dbLastEdited = $existingEdition['last_edited_at'];
                                    $xmlLastEdited = $lastEditedAt;

                                    // Normalize both to comparable format (handle nulls properly)
                                    $dbTimestamp = null;
                                    if ($dbLastEdited) {
                                        $ts = @strtotime($dbLastEdited);
                                        $dbTimestamp = $ts !== false ? $ts : null;
                                    }

                                    $xmlTimestamp = null;
                                    if ($xmlLastEdited) {
                                        $ts = @strtotime($xmlLastEdited);
                                        $xmlTimestamp = $ts !== false ? $ts : null;
                                    }

                                    // Match if both are null, or both are non-null and timestamps match
                                    $lastEditedMatch = ($dbTimestamp === $xmlTimestamp);

                                    // If both UPC and last_edited_at match, skip processing
                                    if ($upcMatch && $lastEditedMatch) {
                                        // Edition unchanged, skip all processing
                                        $importedCount++;
                                        $skippedCount++;
                                        continue;
                                    } else {
                                        // Edition changed
                                        echo "UPDATED: {$externalId} - {$title}\n";
                                    }
                                } else {
                                    // New edition
                                    echo "NEW: {$externalId} - {$title}\n";
                                }

                                // Import film
                                $filmId = $this->importFilm($dvd);

                                // Import film relationships
                                $this->importFilmRelationships($dvd, $filmId);

                                // Import edition
                                $editionId = $this->importEdition($dvd, $filmId);

                                // Import edition relationships
                                $this->importEditionRelationships($dvd, $editionId);

                                $importedCount++;

                                // Free memory immediately
                                unset($dvd, $dvdXml, $dvdXmlWrapped);
                            } else {
                                $errors[] = "Error importing DVD: Failed to parse XML structure";
                            }
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Error importing DVD: " . $e->getMessage();
                    }
                }

                // Stop when Collection closes
                if ($collectionOpen && $reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'Collection') {
                    break;
                }
            }

            $reader->close();

            // Cleanup orphaned editions and films
            if (!empty($importedExternalIds)) {
                $this->repository->deleteOrphanedEditions($importedExternalIds);
            }
            $this->repository->deleteOrphanedFilms();

            // Summary statistics
            echo "\nImport summary:\n";
            echo "  Total processed: {$importedCount}\n";
            echo "  New editions: {$newCount}\n";
            echo "  Updated editions: {$updatedCount}\n";
            echo "  Skipped (unchanged): {$skippedCount}\n";

            $success = empty($errors);
            return new ImportResult($success, $importedCount, $errors, $warnings);
        } finally {
            libxml_clear_errors();
            // Clean up temp file if created
            if ($useTemp && file_exists($utf8File)) {
                unlink($utf8File);
            }
        }
    }

    /**
     * Ensure file is UTF-8 encoded (helper method).
     * Benchmarks command-line iconv vs PHP conversion and uses fastest method.
     * 
     * @param string $filePath Original file path
     * @return string Path to UTF-8 file (original or temp)
     */
    private function ensureUtf8File(string $filePath): string
    {
        // Skip conversion for .utf8.xml files - they're already UTF-8
        if (str_ends_with($filePath, '.utf8.xml')) {
            return $filePath;
        }

        // Read header to detect encoding
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return $filePath;
        }

        $header = fread($handle, 1024);
        fclose($handle);

        if ($header === false) {
            return $filePath;
        }

        // Detect declared encoding
        $srcEnc = 'WINDOWS-1252';
        if (preg_match('/<\?xml[^>]*encoding=["\']([^"\']+)["\']/i', $header, $matches)) {
            $srcEnc = strtoupper($matches[1]);
        }

        // If already UTF-8, return original
        if ($srcEnc === 'UTF-8' || $srcEnc === 'UTF8') {
            return $filePath;
        }

        // Try command-line iconv first (usually faster for large files)
        $tempFile = sys_get_temp_dir() . '/dvdprofiler_' . uniqid() . '.xml';
        $iconvTime = null;
        $iconvSuccess = false;

        // Try iconv command-line tool first
        $iconvCmd = sprintf(
            "iconv -f %s -t UTF-8//TRANSLIT//IGNORE '%s' > '%s' 2>&1",
            escapeshellarg($srcEnc),
            escapeshellarg($filePath),
            escapeshellarg($tempFile)
        );

        $iconvStart = microtime(true);
        exec($iconvCmd, $iconvOutput, $iconvReturnCode);
        $iconvTime = microtime(true) - $iconvStart;

        if ($iconvReturnCode === 0 && file_exists($tempFile) && filesize($tempFile) > 0) {
            // Update XML declaration to UTF-8
            $content = file_get_contents($tempFile, false, null, 0, 2048);
            if ($content !== false) {
                $updatedContent = preg_replace(
                    '/(<\?xml[^>]*encoding=["\'])([^"\']+)(["\'])/i',
                    '$1UTF-8$3',
                    $content,
                    1
                );
                if ($updatedContent !== $content) {
                    file_put_contents($tempFile, $updatedContent . substr(file_get_contents($tempFile), strlen($content)));
                }
            }
            $iconvSuccess = true;
        }

        // Benchmark PHP conversion for comparison (only if iconv succeeded, to compare)
        $phpTime = null;
        if ($iconvSuccess) { // Only benchmark PHP if iconv worked, to compare performance
            $phpStart = microtime(true);
            $phpTempFile = sys_get_temp_dir() . '/dvdprofiler_php_' . uniqid() . '.xml';

            $inHandle = fopen($filePath, 'rb');
            $outHandle = fopen($phpTempFile, 'wb');

            if ($inHandle !== false && $outHandle !== false) {
                // Replace XML declaration with UTF-8 version
                $firstChunk = fread($inHandle, 2048);
                if ($firstChunk !== false) {
                    $firstChunk = preg_replace(
                        '/(<\?xml[^>]*encoding=["\'])([^"\']+)(["\'])/i',
                        '$1UTF-8$3',
                        $firstChunk,
                        1
                    );
                    fwrite($outHandle, $firstChunk);
                }

                // Convert in 2MB chunks
                $chunkSize = 2 * 1024 * 1024;
                while (!feof($inHandle)) {
                    $chunk = fread($inHandle, $chunkSize);
                    if ($chunk === false || $chunk === '') break;

                    if (function_exists('mb_convert_encoding')) {
                        $converted = mb_convert_encoding($chunk, 'UTF-8', $srcEnc);
                    } else {
                        $converted = @iconv($srcEnc, 'UTF-8//TRANSLIT//IGNORE', $chunk) ?: $chunk;
                    }

                    fwrite($outHandle, $converted);
                    unset($chunk, $converted);
                }

                fclose($inHandle);
                fclose($outHandle);
                $phpTime = microtime(true) - $phpStart;

                // Use PHP result if iconv failed or was slower
                if (!$iconvSuccess || $phpTime < $iconvTime) {
                    if ($iconvSuccess && file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                    $tempFile = $phpTempFile;
                    $iconvSuccess = false; // Use PHP result
                } else {
                    // iconv was faster or only option, keep it
                    if (file_exists($phpTempFile)) {
                        unlink($phpTempFile);
                    }
                }
            } else {
                if ($inHandle) fclose($inHandle);
                if ($outHandle) fclose($outHandle);
                if (file_exists($phpTempFile)) unlink($phpTempFile);
                if (!$iconvSuccess) {
                    throw new RuntimeException("Unable to create temp file for encoding conversion");
                }
            }
        }

        // Report timing comparison
        if ($iconvSuccess && $phpTime !== null) {
            echo sprintf(
                "Encoding conversion: iconv=%.2fs, PHP=%.2fs (using %s, %.2fs faster)\n",
                $iconvTime,
                $phpTime,
                $iconvSuccess ? 'iconv' : 'PHP',
                abs($iconvTime - $phpTime)
            );
        } elseif ($iconvSuccess) {
            echo sprintf("Encoding conversion: iconv=%.2fs\n", $iconvTime);
        } elseif ($phpTime !== null) {
            echo sprintf("Encoding conversion: PHP=%.2fs\n", $phpTime);
        }

        return $tempFile;
    }

    /**
     * Import from XML content string.
     *
     * @param string $xmlContent XML content as string
     * @return ImportResult Import results
     */
    public function importFromXmlContent(string $xmlContent): ImportResult
    {
        $xml = simplexml_load_string($xmlContent);
        if ($xml === false) {
            throw new RuntimeException("Invalid XML content");
        }
        return $this->importFromXml($xml);
    }

    /**
     * Import from SimpleXMLElement.
     *
     * @param SimpleXMLElement $xml Parsed XML
     * @return ImportResult Import results
     */
    private function importFromXml(SimpleXMLElement $xml): ImportResult
    {
        $importedCount = 0;
        $errors = [];
        $warnings = [];
        $importedExternalIds = [];

        try {
            foreach ($xml->DVD as $dvd) {
                try {
                    $externalId = (string)$dvd->ID;
                    $title = isset($dvd->Title) ? trim((string)$dvd->Title) : null;

                    // Skip if no title
                    if (empty($title)) {
                        $errors[] = "Error importing DVD {$externalId}: Missing title";
                        continue;
                    }

                    $importedExternalIds[] = $externalId;

                    // Import film
                    $filmId = $this->importFilm($dvd);

                    // Import film relationships
                    $this->importFilmRelationships($dvd, $filmId);

                    // Import edition
                    $editionId = $this->importEdition($dvd, $filmId);

                    // Import edition relationships
                    $this->importEditionRelationships($dvd, $editionId);

                    $importedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Error importing DVD {$dvd->ID}: " . $e->getMessage();
                }
            }

            // Cleanup orphaned editions and films
            if (!empty($importedExternalIds)) {
                $this->repository->deleteOrphanedEditions($importedExternalIds);
            }
            $this->repository->deleteOrphanedFilms();

            $success = empty($errors);
            return new ImportResult($success, $importedCount, $errors, $warnings);
        } catch (\Exception $e) {
            return new ImportResult(false, $importedCount, [$e->getMessage()], $warnings);
        }
    }

    /**
     * Import film from DVD XML element.
     *
     * @param SimpleXMLElement $dvd DVD XML element
     * @return int Film ID
     */
    private function importFilm(SimpleXMLElement $dvd): int
    {
        $title = isset($dvd->Title) ? (string)$dvd->Title : null;
        $sortTitleRaw = isset($dvd->SortTitle) ? (string)$dvd->SortTitle : null;
        $sortTitle = TitleNormalizer::normalizeSeasonNumbers($sortTitleRaw);
        $origTitle = isset($dvd->OriginalTitle) ? (string)$dvd->OriginalTitle : null;
        $yearRaw = isset($dvd->ProductionYear) ? (string)$dvd->ProductionYear : '';
        $year = ($yearRaw !== '' && (int)$yearRaw > 0) ? (int)$yearRaw : null;
        $runtime = isset($dvd->RunningTime) && (string)$dvd->RunningTime !== '' ? (int)$dvd->RunningTime : null;

        // Build candidate normalized titles (use raw sort title for matching to preserve original matching logic)
        $candidates = array_filter([
            TitleNormalizer::normalize($title),
            TitleNormalizer::normalize($sortTitleRaw),
            TitleNormalizer::normalize($origTitle),
        ]);

        $filmId = null;
        $titleNorm = TitleNormalizer::normalize($title);
        if ($year && !empty($candidates)) {
            $filmId = $this->repository->findFilmByTitleAndYear($year, $candidates, $titleNorm);
        }

        // Extract rating info
        $ratingSystem = (string)$dvd->RatingSystem ?: null;
        $rating = (string)$dvd->Rating ?: null;
        $ratingAge = (int)$dvd->RatingAge ?: null;
        $ratingDetails = (string)$dvd->RatingDetails ?: null;

        // Create or update film
        if (!$filmId) {
            $filmId = $this->repository->createFilm([
                'title' => $title,
                'sort_title' => $sortTitle,
                'original_title' => $origTitle,
                'normalized_title' => TitleNormalizer::normalize($title),
                'year' => $year,
                'runtime' => $runtime,
                'rating_system' => $ratingSystem,
                'rating' => $rating,
                'rating_age' => $ratingAge,
                'rating_details' => $ratingDetails,
            ]);
        } else {
            // Merge with existing film data
            $existingFilm = $this->repository->getFilmById($filmId);
            if (!$existingFilm) {
                throw new \RuntimeException("Film with ID {$filmId} not found");
            }

            // Merge titles: prefer title that appears as original_title somewhere
            $mergedTitle = $this->mergeTitle(
                $existingFilm['title'],
                $existingFilm['original_title'],
                $title,
                $origTitle
            );
            $mergedSortTitle = $existingFilm['sort_title'] ?: $sortTitle;
            $mergedOrigTitle = $existingFilm['original_title'] ?: $origTitle;

            // Merge runtime: use shortest (most accurate, longer ones are usually extended cuts)
            $mergedRuntime = $this->mergeRuntime($existingFilm['running_time_min'], $runtime);

            // Fill NULLs for rating fields, but prefer incoming if both exist
            $mergedRatingSystem = $existingFilm['rating_system'] ?: $ratingSystem;
            $mergedRating = $existingFilm['rating'] ?: $rating;
            $mergedRatingAge = $existingFilm['rating_age'] ?: $ratingAge;
            $mergedRatingDetails = $existingFilm['rating_details'] ?: $ratingDetails;

            // Update film with merged data
            $this->repository->updateFilm($filmId, [
                'title' => $mergedTitle,
                'sort_title' => $mergedSortTitle,
                'original_title' => $mergedOrigTitle,
                'normalized_title' => TitleNormalizer::normalize($mergedTitle),
                'running_time_min' => $mergedRuntime,
                'rating_system' => $mergedRatingSystem,
                'rating' => $mergedRating,
                'rating_age' => $mergedRatingAge,
                'rating_details' => $mergedRatingDetails,
            ]);
        }

        return $filmId;
    }

    /**
     * Merge runtime values, preferring shortest (most accurate).
     *
     * @param int|null $existing Existing runtime
     * @param int|null $incoming Incoming runtime
     * @return int|null Merged runtime
     */
    private function mergeRuntime(?int $existing, ?int $incoming): ?int
    {
        if ($existing === null) {
            return $incoming;
        }
        if ($incoming === null) {
            return $existing;
        }
        // Use shortest runtime (most accurate, longer ones are usually extended cuts)
        return min($existing, $incoming);
    }

    /**
     * Merge title values, preferring title that appears as original_title somewhere.
     *
     * @param string|null $existingTitle Existing title
     * @param string|null $existingOrigTitle Existing original title
     * @param string|null $incomingTitle Incoming title
     * @param string|null $incomingOrigTitle Incoming original title
     * @return string|null Merged title
     */
    private function mergeTitle(?string $existingTitle, ?string $existingOrigTitle, ?string $incomingTitle, ?string $incomingOrigTitle): ?string
    {
        // Fill NULLs
        if ($existingTitle === null) {
            return $incomingTitle;
        }
        if ($incomingTitle === null) {
            return $existingTitle;
        }

        // If both exist, prefer title that appears as original_title somewhere
        $existingTitleNorm = TitleNormalizer::normalize($existingTitle);
        $incomingTitleNorm = TitleNormalizer::normalize($incomingTitle);
        $existingOrigTitleNorm = TitleNormalizer::normalize($existingOrigTitle);
        $incomingOrigTitleNorm = TitleNormalizer::normalize($incomingOrigTitle);

        // If incoming title matches existing original_title, prefer incoming
        if ($existingOrigTitleNorm !== null && $incomingTitleNorm === $existingOrigTitleNorm) {
            return $incomingTitle;
        }

        // If existing title matches incoming original_title, prefer existing
        if ($incomingOrigTitleNorm !== null && $existingTitleNorm === $incomingOrigTitleNorm) {
            return $existingTitle;
        }

        // Otherwise, prefer existing (keep first import's title)
        return $existingTitle;
    }

    /**
     * Import film relationships (genres, studios, countries, crew).
     *
     * @param SimpleXMLElement $dvd DVD XML element
     * @param int $filmId Film ID
     */
    private function importFilmRelationships(SimpleXMLElement $dvd, int $filmId): void
    {
        // Genres
        $genres = [];
        if (isset($dvd->Genres->Genre)) {
            foreach ($dvd->Genres->Genre as $genre) {
                $genres[] = (string)$genre;
            }
        }
        $this->repository->syncFilmGenres($filmId, $genres);

        // Studios
        $studios = [];
        if (isset($dvd->Studios->Studio)) {
            foreach ($dvd->Studios->Studio as $studio) {
                $studios[] = (string)$studio;
            }
        }
        $this->repository->syncFilmStudios($filmId, $studios);

        // Countries
        $countries = [
            1 => (string)$dvd->CountryOfOrigin ?: null,
            2 => (string)$dvd->CountryOfOrigin2 ?: null,
            3 => (string)$dvd->CountryOfOrigin3 ?: null,
        ];
        $countries = array_filter($countries);
        $this->repository->syncFilmCountries($filmId, $countries);

        // Crew (Directors & Producers)
        $crew = [];
        if (isset($dvd->Credits->Credit)) {
            foreach ($dvd->Credits->Credit as $credit) {
                $creditType = (string)$credit->CreditType;
                $creditSubtype = (string)$credit->CreditSubtype;

                // Only import Directors and Producers
                $isDirector = ($creditType === 'Direction' && $creditSubtype === 'Director');
                $isProducer = ($creditType === 'Production' && in_array($creditSubtype, ['Producer', 'Executive Producer'], true));

                if ($isDirector || $isProducer) {
                    $crew[] = [
                        'first_name' => (string)$credit->FirstName ?: null,
                        'middle_name' => (string)$credit->MiddleName ?: null,
                        'last_name' => (string)$credit->LastName ?: null,
                        'birth_year' => (int)$credit->BirthYear ?: null,
                        'role_type' => $creditSubtype,
                        'credited_as' => (string)$credit->CreditedAs ?: null,
                    ];
                }
            }
        }
        $this->repository->syncFilmCrew($filmId, $crew);
    }

    /**
     * Import edition from DVD XML element.
     *
     * @param SimpleXMLElement $dvd DVD XML element
     * @param int $filmId Film ID
     * @return int Edition ID
     */
    private function importEdition(SimpleXMLElement $dvd, int $filmId): int
    {
        $externalId = (string)$dvd->ID;

        // Extract distributor (first MediaCompany)
        $distributor = null;
        if (isset($dvd->MediaCompanies->MediaCompany)) {
            $distributor = trim((string)$dvd->MediaCompanies->MediaCompany[0]);
            $distributor = $distributor === '' ? null : $distributor;
        }

        // Extract last_edited_at
        $lastEditedAt = null;
        if (isset($dvd->LastEdited)) {
            $lastEditedStr = trim((string)$dvd->LastEdited);
            if ($lastEditedStr) {
                $timestamp = strtotime($lastEditedStr);
                if ($timestamp !== false) {
                    $lastEditedAt = date('c', $timestamp);
                }
            }
        }

        // Extract case info
        $caseType = (string)$dvd->CaseType ?: null;
        $caseSlipCover = null;
        if (isset($dvd->CaseSlipCover)) {
            $caseSlipCoverValue = filter_var($dvd->CaseSlipCover, FILTER_VALIDATE_BOOLEAN);
            $caseSlipCover = $caseSlipCoverValue ? 1 : 0;
        }

        // Extract other features
        $otherFeatures = null;
        if (isset($dvd->Features->OtherFeatures)) {
            $otherFeaturesText = trim((string)$dvd->Features->OtherFeatures);
            $otherFeatures = $otherFeaturesText === '' ? null : $otherFeaturesText;
        }

        // Extract media types from MediaTypes (DVD Profiler uses <DVD>, <BluRay>, <UltraHD>; we store DVD, BLURAY, UHD)
        $mediaTypes = [];
        if (isset($dvd->MediaTypes)) {
            if (isset($dvd->MediaTypes->DVD) && filter_var($dvd->MediaTypes->DVD, FILTER_VALIDATE_BOOLEAN)) {
                $mediaTypes[] = 'DVD';
            }
            if (isset($dvd->MediaTypes->BluRay) && filter_var($dvd->MediaTypes->BluRay, FILTER_VALIDATE_BOOLEAN)) {
                $mediaTypes[] = 'BLURAY';
            }
            // DVD Profiler exports <UltraHD>, not <UHD>
            if ((isset($dvd->MediaTypes->UltraHD) && filter_var($dvd->MediaTypes->UltraHD, FILTER_VALIDATE_BOOLEAN))
                || (isset($dvd->MediaTypes->UHD) && filter_var($dvd->MediaTypes->UHD, FILTER_VALIDATE_BOOLEAN))) {
                $mediaTypes[] = 'UHD';
            }
        }
        
        $editionId = $this->repository->upsertEdition([
            'film_id' => $filmId,
            'external_id' => $externalId,
            'external_id_base' => (string)$dvd->ID_Base,
            'external_id_type' => (string)$dvd->ID_Type,
            'external_locality_id' => (int)$dvd->ID_LocalityID ?: null,
            'external_variant_num' => (int)$dvd->ID_VariantNum ?: null,
            'upc' => (string)$dvd->UPC ?: null,
            'release_date' => (string)$dvd->Released ?: null,
            'name' => isset($dvd->DistTrait) ? (trim((string)$dvd->DistTrait) ?: null) : null,
            'distributor' => $distributor,
            'last_edited_at' => $lastEditedAt,
            'case_type' => $caseType,
            'case_slip_cover' => $caseSlipCover,
            'other_features' => $otherFeatures,
        ]);
        
        // Sync media types to edition_media_type (DVD, BLURAY, UHD only)
        $this->repository->syncEditionMediaTypes($editionId, $mediaTypes);
        
        return $editionId;
    }

    /**
     * Import edition relationships (regions, subtitles, features, discs, audio, video).
     *
     * @param SimpleXMLElement $dvd DVD XML element
     * @param int $editionId Edition ID
     */
    private function importEditionRelationships(SimpleXMLElement $dvd, int $editionId): void
    {
        // Regions
        $regions = [];
        if (isset($dvd->Regions->Region)) {
            foreach ($dvd->Regions->Region as $region) {
                $regions[] = (string)$region;
            }
        }
        $this->repository->syncEditionRegions($editionId, $regions);

        // Subtitles
        $subtitles = [];
        if (isset($dvd->Subtitles->Subtitle)) {
            foreach ($dvd->Subtitles->Subtitle as $subtitle) {
                $subtitles[] = (string)$subtitle;
            }
        }
        $this->repository->syncEditionSubtitles($editionId, $subtitles);

        // Features
        $features = [];
        if (isset($dvd->Features)) {
            foreach (self::FEATURE_NAMES as $featureName) {
                if (isset($dvd->Features->$featureName)) {
                    $featureValue = filter_var($dvd->Features->$featureName, FILTER_VALIDATE_BOOLEAN);
                    if ($featureValue) {
                        $features[] = $featureName;
                    }
                }
            }
        }
        $this->repository->syncEditionFeatures($editionId, $features);

        // Discs
        $discs = [];
        if (isset($dvd->Discs->Disc)) {
            $discNumber = 1;
            foreach ($dvd->Discs->Disc as $disc) {
                // Build notes for side info only when there is content on side B
                $notes = null;
                $hasSideB = isset($disc->DescriptionSideB) && trim((string)$disc->DescriptionSideB) !== ''
                    || isset($disc->DiscIDSideB) && trim((string)$disc->DiscIDSideB) !== '';
                if ($hasSideB) {
                    $notes = sprintf(
                        "Side A: %s | Side B: %s",
                        (string)($disc->DescriptionSideA ?? '') ?: 'N/A',
                        (string)($disc->DescriptionSideB ?? '') ?: 'N/A'
                    );
                }

                // Use primary side description as role
                $role = null;
                if (isset($disc->DescriptionSideA)) {
                    $role = (string)$disc->DescriptionSideA;
                } elseif (isset($disc->DescriptionSideB)) {
                    $role = (string)$disc->DescriptionSideB;
                }

                // Use primary side label
                $label = null;
                if (isset($disc->LabelSideA)) {
                    $label = (string)$disc->LabelSideA;
                } elseif (isset($disc->LabelSideB)) {
                    $label = (string)$disc->LabelSideB;
                }

                $discs[] = [
                    'disc_number' => $discNumber,
                    'role' => $role ?: null,
                    'label' => $label ?: null,
                    'notes' => $notes,
                ];

                $discNumber++;
            }
        }
        $this->repository->syncEditionDiscs($editionId, $discs);

        // Audio tracks
        $audioTracks = [];
        if (isset($dvd->Audio->AudioTrack)) {
            foreach ($dvd->Audio->AudioTrack as $track) {
                $content = trim((string)$track->AudioContent);
                $isDescriptive = strcasecmp($content, 'Audio Descriptive') === 0;

                // Normalize empty strings to null for text columns
                $language = $isDescriptive ? null : ($content === '' ? null : $content);
                $channelLayout = trim((string)$track->AudioChannels);
                $channelLayout = $channelLayout === '' ? null : $channelLayout;
                $format = trim((string)$track->AudioFormat);
                $format = $format === '' ? null : $format;

                $audioTracks[] = [
                    'language' => $language,
                    'channel_layout' => $channelLayout,
                    'format' => $format,
                    'is_descriptive' => $isDescriptive ? 1 : 0,
                ];
            }
        }
        $this->repository->syncAudioTracks($editionId, $audioTracks);

        // Video format
        if (isset($dvd->Format)) {
            $fmt = $dvd->Format;
            $this->repository->upsertVideoFormat([
                'edition_id' => $editionId,
                'is_color' => filter_var($fmt->ColorFormat->ClrColor, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'is_black_and_white' => filter_var($fmt->ColorFormat->ClrBlackAndWhite, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'is_colorized' => filter_var($fmt->ColorFormat->ClrColorized, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'is_mixed_color' => filter_var($fmt->ColorFormat->ClrMixed, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'is_2d' => filter_var($fmt->Dimensions->Dim2D, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'is_3d_anaglyph' => filter_var($fmt->Dimensions->Dim3DAnaglyph, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'is_3d_bluray' => filter_var($fmt->Dimensions->Dim3DBluRay, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'is_16x9' => filter_var($fmt->Format16X9, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'aspect_ratio' => $fmt->FormatAspectRatio !== null ? (float)$fmt->FormatAspectRatio : null,
                'is_full_frame' => filter_var($fmt->FormatFullFrame, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'is_letterbox' => filter_var($fmt->FormatLetterBox, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'is_pan_and_scan' => filter_var($fmt->FormatPanAndScan, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'is_dual_layered' => filter_var($fmt->FormatDualLayered, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'is_dual_sided' => filter_var($fmt->FormatDualSided, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'video_standard' => (string)$fmt->FormatVideoStandard ?: null,
            ]);
        }
    }
}
