<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use App\Infrastructure\Database\SchemaMiddleware;

/**
 * Main router - registers all module routes.
 */
return function (App $app) {
    // Root redirect to main site
    $app->get('/', function ($request, $response) {
        return $response
            ->withHeader('Location', 'https://mathieulalonde.com')
            ->withStatus(301);
    });

    // Shared routes (health, users) - no prefix, no schema switching
    $registerSharedRoutes = require __DIR__ . '/../Shared/Routes/routes.php';
    $registerSharedRoutes($app);

    // Collections routes - prefixed with /collections, uses collections schema
    $app->group('/collections', function (RouteCollectorProxy $group) {
        $registerCollectionsRoutes = require __DIR__ . '/../Collections/Routes/routes.php';
        $registerCollectionsRoutes($group);
    })->add(new SchemaMiddleware($app->getContainer(), 'collections'));
};
