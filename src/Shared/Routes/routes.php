<?php

declare(strict_types=1);

use Slim\App;
use App\Shared\Controller\HealthController;
use App\Shared\Controller\UserController;

/**
 * Register shared routes (health, users).
 * These routes are NOT prefixed.
 */
return function (App $app) {
    // Health check routes
    $app->get('/health', [HealthController::class, 'status']);
    $app->get('/health/db', [HealthController::class, 'database']);

    // User CRUD routes
    $app->get('/users', [UserController::class, 'list']);
    $app->post('/users', [UserController::class, 'create']);
    $app->get('/users/{id}', [UserController::class, 'getById']);
    $app->put('/users/{id}', [UserController::class, 'update']);
    $app->delete('/users/{id}', [UserController::class, 'delete']);
};
