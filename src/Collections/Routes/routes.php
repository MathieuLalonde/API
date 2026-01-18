<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use App\Collections\Controller\FilmController;
use App\Collections\Controller\EditionController;
use App\Collections\Controller\ImportController;

/**
 * Register Collections-specific routes.
 * These routes are prefixed with /collections in the main router.
 */
return function (RouteCollectorProxy $group) {
    // Film routes
    $group->get('/films', [FilmController::class, 'list']);
    $group->get('/films/{id}', [FilmController::class, 'show']);

    // Edition routes
    $group->get('/editions/{id}', [EditionController::class, 'show']);

    // Import routes
    $group->post('/import', [ImportController::class, 'import']);
};
