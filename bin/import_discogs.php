#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * CLI script for importing Discogs LP collection.
 * Thin wrapper that bootstraps DI container and calls DiscogsImportService.
 */

// Bootstrap autoloader
require __DIR__ . '/../vendor/autoload.php';

// Load environment configuration
use App\Config\AppConfig;
use App\Bootstrap\ContainerFactory;
use App\Collections\Service\DiscogsImportService;

AppConfig::load();

// Get username from environment
$username = AppConfig::get('DISCOGS_USERNAME');
if (!$username) {
    fwrite(STDERR, "Error: DISCOGS_USERNAME environment variable is not set\n");
    fwrite(STDERR, "Please set DISCOGS_USERNAME in your .env file or environment\n");
    exit(1);
}

// Create DI container
$container = ContainerFactory::create();

// Get DiscogsImportService from container
$importService = $container->get(DiscogsImportService::class);

// Import from Discogs API
try {
    echo "Starting import from Discogs for user: {$username}\n";
    
    $startTime = microtime(true);
    $result = $importService->importFromDiscogs($username);
    $elapsed = round(microtime(true) - $startTime, 2);
    echo "Import processing time: {$elapsed}s\n";

    if ($result->success) {
        echo "Import complete!\n";
        echo "Processed: {$result->importedCount} release(s)\n";
        if (!empty($result->warnings)) {
            echo "Warnings:\n";
            foreach ($result->warnings as $warning) {
                echo "  - {$warning}\n";
            }
        }
        exit(0);
    } else {
        echo "Import completed with errors!\n";
        echo "Processed: {$result->importedCount} release(s)\n";
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
