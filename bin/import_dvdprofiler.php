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
$host = getenv('PG_HOST') ?: getenv('DB_HOST') ?: 'localhost';
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
  $title = preg_replace('/^(the|a|an)\s+/i', '', $title);

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

echo "Import complete.\n";
