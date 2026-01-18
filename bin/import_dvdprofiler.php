#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * CLI script for importing DVD Profiler XML exports.
 * Thin wrapper that bootstraps DI container and calls ImportService.
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php import_dvdprofiler.php <export.xml> [--non-streaming]\n");
    fwrite(STDERR, "  --non-streaming: Use full file load instead of streaming (for testing)\n");
    exit(1);
}

$xmlFile = $argv[1];
$forceNonStreaming = isset($argv[2]) && $argv[2] === '--non-streaming';

if (!file_exists($xmlFile)) {
    fwrite(STDERR, "Error: File not found: {$xmlFile}\n");
    exit(1);
}

// Increase memory limit for large XML files
ini_set('memory_limit', '512M');

// Bootstrap autoloader
require __DIR__ . '/../vendor/autoload.php';

// Load environment configuration
use App\Config\AppConfig;
use App\Bootstrap\ContainerFactory;
use App\Collections\Service\ImportService;

AppConfig::load();

// Create DI container
$container = ContainerFactory::create();

// Get ImportService from container
$importService = $container->get(ImportService::class);

// Import from XML file
try {
    echo "Starting import from: {$xmlFile}\n";
    $fileSize = filesize($xmlFile);
    echo "File size: " . round($fileSize / 1024 / 1024, 2) . " MB\n";
    
    // For large files (> 10MB), suggest pre-conversion
    if ($fileSize > 10 * 1024 * 1024) {
        echo "\nLarge file detected. If import hangs, try pre-converting to UTF-8:\n";
        echo "  php bin/convert_to_utf8.php {$xmlFile} {$xmlFile}.utf8.xml\n";
        echo "  Then import the .utf8.xml file\n";
    }
    
    $startTime = microtime(true);
    $result = $importService->importFromXmlFile($xmlFile, $forceNonStreaming);
    $elapsed = round(microtime(true) - $startTime, 2);
    echo "Import processing time: {$elapsed}s\n";

    if ($result->success) {
        echo "Import complete!\n";
        echo "Processed: {$result->importedCount} edition(s)\n";
        if (!empty($result->warnings)) {
            echo "Warnings:\n";
            foreach ($result->warnings as $warning) {
                echo "  - {$warning}\n";
            }
        }
        exit(0);
    } else {
        echo "Import completed with errors!\n";
        echo "Processed: {$result->importedCount} edition(s)\n";
        if (!empty($result->errors)) {
            echo "Errors:\n";
            foreach ($result->errors as $error) {
                echo "  - {$error}\n";
            }
        }
        if (!empty($result->warnings)) {
            echo "Warnings:\n";
            foreach ($result->warnings as $warning) {
                echo "  - {$warning}\n";
            }
        }
        exit(1);
    }
} catch (\Exception $e) {
    fwrite(STDERR, "Fatal error: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
