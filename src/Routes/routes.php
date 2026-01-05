<?php

declare(strict_types=1);

use Slim\App;
use App\Controller\HealthController;
use App\Controller\UserController;
use App\Controller\FilmController;
use App\Controller\EditionController;

/**
 * Register all application routes.
 */
return function (App $app) {
  // Root redirect to main site
  $app->get('/', function ($request, $response) {
    return $response
        ->withHeader('Location', 'https://mathieulalonde.com')
        ->withStatus(301);
  });

  // Health check routes
  $app->get('/health', [HealthController::class, 'status']);
  $app->get('/health/db', [HealthController::class, 'database']);

  // User CRUD routes
  $app->get('/users', [UserController::class, 'list']);
  $app->post('/users', [UserController::class, 'create']);
  $app->get('/users/{id}', [UserController::class, 'getById']);
  $app->put('/users/{id}', [UserController::class, 'update']);
  $app->delete('/users/{id}', [UserController::class, 'delete']);

  // Film routes
  $app->get('/films', [FilmController::class, 'list']);
  $app->get('/films/{id}', [FilmController::class, 'show']);

  // Edition routes
  $app->get('/editions/{id}', [EditionController::class, 'show']);
};
