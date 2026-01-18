#!/usr/bin/env php
<?php
/**
 * Helper script to convert DVD Profiler XML file to UTF-8.
 * This is much faster than converting during import for large files.
 * 
 * Usage: php convert_to_utf8.php input.xml output.xml
 */

if ($argc < 3) {
    fwrite(STDERR, "Usage: php convert_to_utf8.php <input.xml> <output.xml>\n");
    exit(1);
}

$inputFile = $argv[1];
$outputFile = $argv[2];

if (!file_exists($inputFile)) {
    fwrite(STDERR, "Error: Input file not found: {$inputFile}\n");
    exit(1);
}

$fileSize = filesize($inputFile);
echo "Converting {$inputFile} to UTF-8...\n";
echo "File size: " . round($fileSize / 1024 / 1024, 2) . " MB\n";

$startTime = microtime(true);

// Read header to detect encoding
$handle = fopen($inputFile, 'rb');
$header = fread($handle, 1024);
fclose($handle);

$srcEnc = 'WINDOWS-1252';
if (preg_match('/<\?xml[^>]*encoding=["\']([^"\']+)["\']/i', $header, $matches)) {
    $srcEnc = strtoupper($matches[1]);
}

echo "Detected encoding: {$srcEnc}\n";

if ($srcEnc === 'UTF-8' || $srcEnc === 'UTF8') {
    echo "File is already UTF-8. Copying to output...\n";
    $copyStart = microtime(true);
    copy($inputFile, $outputFile);
    $copyTime = microtime(true) - $copyStart;
    echo sprintf("Done! Copy time: %.2fs\n", $copyTime);
    exit(0);
}

// Benchmark: Try iconv command first (usually faster for large files)
$iconvStart = microtime(true);
$iconvCmd = sprintf(
    "iconv -f %s -t UTF-8//TRANSLIT//IGNORE '%s' > '%s' 2>&1",
    escapeshellarg($srcEnc),
    escapeshellarg($inputFile),
    escapeshellarg($outputFile)
);
exec($iconvCmd, $output, $returnCode);
$iconvTime = microtime(true) - $iconvStart;

$iconvSuccess = ($returnCode === 0 && file_exists($outputFile) && filesize($outputFile) > 0);

if ($iconvSuccess) {
    // Update XML declaration to UTF-8
    $updateStart = microtime(true);
    $content = file_get_contents($outputFile, false, null, 0, 2048);
    if ($content !== false) {
        $updatedContent = preg_replace(
            '/(<\?xml[^>]*encoding=["\'])([^"\']+)(["\'])/i',
            '$1UTF-8$3',
            $content,
            1
        );
        if ($updatedContent !== $content) {
            file_put_contents($outputFile, $updatedContent . substr(file_get_contents($outputFile), strlen($content)));
        }
    }
    $updateTime = microtime(true) - $updateStart;
    
    $totalTime = microtime(true) - $startTime;
    echo sprintf(
        "Conversion complete using iconv! (iconv=%.2fs, update=%.2fs, total=%.2fs)\n",
        $iconvTime,
        $updateTime,
        $totalTime
    );
    exit(0);
}

// Fall back to PHP conversion
echo "iconv failed or not available, using PHP conversion...\n";
if ($iconvSuccess === false) {
    echo sprintf("  iconv attempt took %.2fs and failed\n", $iconvTime);
}

$phpStart = microtime(true);
$inHandle = fopen($inputFile, 'rb');
$outHandle = fopen($outputFile, 'wb');

if ($inHandle === false || $outHandle === false) {
    fwrite(STDERR, "Error: Unable to open files for PHP conversion\n");
    exit(1);
}

// Replace XML declaration
$firstChunk = fread($inHandle, 2048);
$firstChunk = preg_replace(
    '/(<\?xml[^>]*encoding=["\'])([^"\']+)(["\'])/i',
    '$1UTF-8$3',
    $firstChunk,
    1
);
fwrite($outHandle, $firstChunk);

// Convert in chunks
$chunkSize = 2 * 1024 * 1024; // 2MB chunks
$bytesProcessed = strlen($firstChunk);
$lastProgressTime = $phpStart;
$progressInterval = 5.0; // Show progress every 5 seconds

while (!feof($inHandle)) {
    $chunk = fread($inHandle, $chunkSize);
    if ($chunk === false || $chunk === '') break;
    
    $convertChunkStart = microtime(true);
    if (function_exists('mb_convert_encoding')) {
        $converted = mb_convert_encoding($chunk, 'UTF-8', $srcEnc);
    } else {
        $converted = @iconv($srcEnc, 'UTF-8//TRANSLIT//IGNORE', $chunk) ?: $chunk;
    }
    $convertTime = microtime(true) - $convertChunkStart;
    
    fwrite($outHandle, $converted);
    $bytesProcessed += strlen($chunk);
    
    // Show progress every 5 seconds
    $currentTime = microtime(true);
    if ($currentTime - $lastProgressTime >= $progressInterval) {
        $mbProcessed = round($bytesProcessed / 1024 / 1024, 1);
        $totalMB = round($fileSize / 1024 / 1024, 1);
        $percent = $fileSize > 0 ? round(100 * $bytesProcessed / $fileSize, 1) : 0;
        echo sprintf(
            "  Processed %s / %s MB (%.1f%%) - %.1f MB/s\n",
            $mbProcessed,
            $totalMB,
            $percent,
            $bytesProcessed / ($currentTime - $phpStart) / 1024 / 1024
        );
        $lastProgressTime = $currentTime;
    }
    
    unset($chunk, $converted);
}

fclose($inHandle);
fclose($outHandle);

$phpTime = microtime(true) - $phpStart;
$totalTime = microtime(true) - $startTime;

echo sprintf(
    "Conversion complete using PHP! (PHP=%.2fs, total=%.2fs",
    $phpTime,
    $totalTime
);

if ($iconvSuccess === false && $iconvTime > 0) {
    echo sprintf(", iconv attempt=%.2fs (failed)", $iconvTime);
}

echo ")\n";
