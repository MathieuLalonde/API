#!/usr/bin/env php
<?php

if ($argc < 2) {
  fwrite(STDERR, "Usage: php import_dvdprofiler.php <export.xml>\n");
  exit(1);
}

$xmlFile = $argv[1];

if (!file_exists($xmlFile)) {
  throw new RuntimeException("File not found: $xmlFile");
}

/*
|--------------------------------------------------------------------------
| Load .env into environment (optional; no dependency required)
|--------------------------------------------------------------------------
*/
function loadDotEnv(string $path): void
{
  if (!is_file($path)) {
    return;
  }

  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
      continue;
    }
    if (strpos($line, '=') === false) {
      continue;
    }
    list($name, $value) = explode('=', $line, 2);
    $name = trim($name);
    $value = trim($value);

    // remove surrounding quotes
    if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
      $value = substr($value, 1, -1);
    }

    putenv("$name=$value");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
  }
}

// load .env from repo root (one level up from bin/)
loadDotEnv(dirname(__DIR__) . '/.env');

/*
|--------------------------------------------------------------------------
| Database connection (use .env values when present)
|--------------------------------------------------------------------------
*/
// $host = getenv('PG_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$host = 'localhost';
$port = getenv('PG_PORT') ?: '5432';
$db   = getenv('PG_DB') ?: 'dvdprofiler';
$user = getenv('PG_USER') ?: 'postgres';
$pass = getenv('PG_PASSWORD') ?: getenv('DB_PASSWORD') ?: 'postgres';

$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $db);

$pdo = new PDO(
  $dsn,
  $user,
  $pass,
  [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]
);

// ensure PDO client uses UTF-8
$pdo->exec("SET client_encoding TO 'UTF8'");

/*
|--------------------------------------------------------------------------
| Load XML and handle encoding
|--------------------------------------------------------------------------
*/
$content = file_get_contents($xmlFile);
if ($content === false) {
    throw new RuntimeException("Unable to read $xmlFile");
}
// detect declared encoding (fallback to WINDOWS-1252)
if (preg_match('/<\?xml[^>]*encoding=["\']([^"\']+)["\']/', $content, $m)) {
    $srcEnc = strtoupper($m[1]);
} else {
    $srcEnc = 'WINDOWS-1252';
}

if ($srcEnc !== 'UTF-8') {
    // convert (use iconv or mb_convert_encoding)
    $content = @iconv($srcEnc, 'UTF-8//TRANSLIT', $content) ?: mb_convert_encoding($content, 'UTF-8', $srcEnc);
    // update XML header to UTF-8 so parsers know
    $content = preg_replace('/(<\?xml[^>]*encoding=["\'])([^"\']+)(["\'])/i', '$1UTF-8$3', $content, 1);
}
$xml = simplexml_load_string($content);
if ($xml === false) {
  throw new RuntimeException("Invalid XML");
}

/*
|--------------------------------------------------------------------------
| Track imported IDs
|--------------------------------------------------------------------------
*/
$importedExternalIds = [];

/*
|--------------------------------------------------------------------------
| Normalize film title function
|--------------------------------------------------------------------------
*/

function normalizeTitle(?string $title): ?string
{
  if (!$title) {
    return null;
  }

  $title = mb_strtolower($title, 'UTF-8');

  // Remove accents
  $title = iconv('UTF-8', 'ASCII//TRANSLIT', $title);

  // Remove leading articles
  $title = preg_replace('/^(the|a|an|le|la|les|l\')\s+/i', '', $title);

  // Remove punctuation
  $title = preg_replace('/[^a-z0-9\s]/', '', $title);

  // Collapse whitespace
  $title = preg_replace('/\s+/', ' ', trim($title));

  return $title;
}

/*
|--------------------------------------------------------------------------
| Prepare statements
|--------------------------------------------------------------------------
*/
$findFilmStmt = $pdo->prepare("
  SELECT id
  FROM film
  WHERE production_year = :year
    AND normalized_title = ANY(:candidates)
  LIMIT 1
");

$insertFilmStmt = $pdo->prepare("
  INSERT INTO film (
    title,
    sort_title,
    original_title,
    normalized_title,
    production_year,
    running_time_min
  )
  VALUES (
    :title,
    :sort_title,
    :original_title,
    :normalized_title,
    :year,
    :runtime
  )
  RETURNING id
");

$editionUpsertStmt = $pdo->prepare("
    INSERT INTO edition (
        film_id,
        external_id,
        external_id_base,
        external_id_type,
        external_locality_id,
        external_variant_num,
        upc,
        release_date,
        media_type
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
        :media_type
    )
    ON CONFLICT (external_id)
    DO UPDATE SET
        upc = EXCLUDED.upc,
        release_date = EXCLUDED.release_date,
        media_type = EXCLUDED.media_type
    RETURNING id
");

$deleteEditionDiscStmt = $pdo->prepare("
    DELETE FROM edition_disc WHERE edition_id = ?
");

$insertEditionDiscStmt = $pdo->prepare("
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

$deleteAudioStmt = $pdo->prepare("
    DELETE FROM audio_track WHERE edition_id = ?
");

$insertAudioStmt = $pdo->prepare("
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

$upsertVideoStmt = $pdo->prepare("
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

/*
|--------------------------------------------------------------------------
| Iterate titles
|--------------------------------------------------------------------------
*/
foreach ($xml->DVD as $dvd) {

  // ---- Identity ----
  $externalId = (string)$dvd->ID;
  $importedExternalIds[] = $externalId;

  // ---- Film ----
  $title     = (string)$dvd->Title ?: null;
  $sortTitle = (string)$dvd->SortTitle ?: null;
  $origTitle = (string)$dvd->OriginalTitle ?: null;
  $year      = (int)$dvd->ProductionYear ?: null;
  $runtime   = (int)$dvd->RunningTime ?: null;

  // Build candidate normalized titles
  $candidates = array_filter([
    normalizeTitle($title),
    normalizeTitle($sortTitle),
    normalizeTitle($origTitle)
  ]);

  $filmId = null;

  if ($year && !empty($candidates)) {
    $findFilmStmt->execute([
      'year'       => $year,
      'candidates' => '{' . implode(',', array_map(fn($v) => '"' . $v . '"', $candidates)) . '}'
    ]);

    $filmId = $findFilmStmt->fetchColumn();
  }

  // Insert if not found
  if (!$filmId) {
    $insertFilmStmt->execute([
      'title'            => $title,
      'sort_title'       => $sortTitle,
      'original_title'   => $origTitle,
      'normalized_title' => normalizeTitle($title),
      'year'             => $year,
      'runtime'          => $runtime
    ]);

    $filmId = $insertFilmStmt->fetchColumn();
  }

  // ---- Edition ----
  $editionUpsertStmt->execute([
    'film_id'              => $filmId,
    'external_id'          => $externalId,
    'external_id_base'     => (string)$dvd->ID_Base,
    'external_id_type'     => (string)$dvd->ID_Type,
    'external_locality_id' => (int)$dvd->ID_LocalityID ?: null,
    'external_variant_num' => (int)$dvd->ID_VariantNum ?: null,
    'upc'                  => (string)$dvd->UPC ?: null,
    'release_date'         => (string)$dvd->Released ?: null,
    'media_type'           => (string)$dvd->MediaTypes->MediaType ?? 'UNKNOWN'
  ]);

  $editionId = $editionUpsertStmt->fetchColumn();

  // ---- Discs (labels only - no technical data ownership) ----
  // Rule: Create edition_disc rows only as organizational labels.
  // Technical data (audio, video, features) stays at edition level.
  // Do NOT push edition data down to discs.
  $deleteEditionDiscStmt->execute([$editionId]);

  if (isset($dvd->Discs->Disc)) {
      $discNumber = 1;
      foreach ($dvd->Discs->Disc as $disc) {

          // Build notes for side info if dual-sided
          $notes = null;
          if (isset($disc->DiscIDSideA) && isset($disc->DiscIDSideB)) {
              $notes = sprintf(
                  "Side A: %s | Side B: %s",
                  (string)$disc->DescriptionSideA ?: 'N/A',
                  (string)$disc->DescriptionSideB ?: 'N/A'
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

          // Create one edition_disc row per physical disc
          $insertEditionDiscStmt->execute([
              'edition_id'   => $editionId,
              'disc_number'  => $discNumber,
              'role'         => $role ?: null,
              'label'        => $label ?: null,
              'notes'        => $notes,
          ]);

          $discNumber++;
      }
  }

  // Note: Physical disc metadata (fingerprints, dual-layer/sided)
  // is NOT imported unless explicitly needed. Edition-level data is sufficient.

  // ---- Audio ----
  $deleteAudioStmt->execute([$editionId]);

  if (isset($dvd->Audio->AudioTrack)) {
      foreach ($dvd->Audio->AudioTrack as $track) {

            $content = trim((string)$track->AudioContent);

            $isDescriptive = strcasecmp($content, 'Audio Descriptive') === 0;

            // normalize empty strings to null for text columns
            $language = $isDescriptive ? null : ($content === '' ? null : $content);
            $channelLayout = trim((string)$track->AudioChannels);
            $channelLayout = $channelLayout === '' ? null : $channelLayout;
            $format = trim((string)$track->AudioFormat);
            $format = $format === '' ? null : $format;

            $insertAudioStmt->execute([
              'edition_id'     => $editionId,
              'language'       => $language,
              'channel_layout' => $channelLayout,
              'format'         => $format,
              'is_descriptive' => $isDescriptive ? 1 : 0,
            ]);
      }
  }

  // ---- Video ----
  if (isset($dvd->Format)) {
      $fmt = $dvd->Format;

      $upsertVideoStmt->execute([
          'edition_id'         => $editionId,

          'is_color'           => filter_var($fmt->ColorFormat->ClrColor, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
          'is_black_and_white' => filter_var($fmt->ColorFormat->ClrBlackAndWhite, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
          'is_colorized'       => filter_var($fmt->ColorFormat->ClrColorized, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
          'is_mixed_color'     => filter_var($fmt->ColorFormat->ClrMixed, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,

          'is_2d'              => filter_var($fmt->Dimensions->Dim2D, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
          'is_3d_anaglyph'     => filter_var($fmt->Dimensions->Dim3DAnaglyph, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
          'is_3d_bluray'       => filter_var($fmt->Dimensions->Dim3DBluRay, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,

          'is_16x9'            => filter_var($fmt->Format16X9, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
          'aspect_ratio'       => $fmt->FormatAspectRatio !== null
                                    ? (float)$fmt->FormatAspectRatio
                                    : null,
          'is_full_frame'      => filter_var($fmt->FormatFullFrame, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
          'is_letterbox'       => filter_var($fmt->FormatLetterBox, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
          'is_pan_and_scan'    => filter_var($fmt->FormatPanAndScan, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,

          'is_dual_layered'    => filter_var($fmt->FormatDualLayered, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
          'is_dual_sided'      => filter_var($fmt->FormatDualSided, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,

          'video_standard'     => (string)$fmt->FormatVideoStandard ?: null,
      ]);
  }

  echo "Imported edition {$externalId} (edition_id={$editionId})\n";
}

/*
|--------------------------------------------------------------------------
| Delete removed editions
|--------------------------------------------------------------------------
*/
if (!empty($importedExternalIds)) {
  $placeholders = implode(',', array_fill(0, count($importedExternalIds), '?'));

  $deleteStmt = $pdo->prepare("
        DELETE FROM edition
        WHERE external_id NOT IN ($placeholders)
    ");

  $deleteStmt->execute($importedExternalIds);
}

/*
|--------------------------------------------------------------------------
| Delete orphan films
|--------------------------------------------------------------------------
*/
$pdo->exec("
    DELETE FROM film
    WHERE id NOT IN (
        SELECT DISTINCT film_id FROM edition
    )
");

echo "\nImport complete.\n";
