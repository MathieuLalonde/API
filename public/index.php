<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use App\Config\AppConfig;
use App\Bootstrap\ContainerFactory;
use App\Infrastructure\Http\CorsMiddleware;

// Load environment configuration
AppConfig::load();

// Create DI container
$container = ContainerFactory::create();

// Create app with DI container
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add CORS middleware before routes
$app->add(new CorsMiddleware());

// Mark all responses as uncacheable (SiteGround proxy otherwise caches GETs)
$app->add(new NoCacheMiddleware());

// Register routes
$registerRoutes = require __DIR__ . '/../src/Routes/routes.php';
$registerRoutes($app);

// Error handling middleware
$app->addErrorMiddleware(
    AppConfig::isDev(),
    true,
    true,
    null
);

$app->run();
