<?php
declare(strict_types=1);

use Slim\App;
use App\Controller\HealthController;
use App\Controller\UserController;
use App\Controller\DiagnosticsController;

/**
 * Register all application routes.
 */
return function (App $app) {
    // Health check routes
    $app->get('/health', [HealthController::class, 'status']);
    $app->get('/health/db', [HealthController::class, 'database']);
    
    // Diagnostics routes
    $app->get('/_php_version', [DiagnosticsController::class, 'phpVersion']);

    // User CRUD routes
    $app->get('/users', [UserController::class, 'list']);
    $app->post('/users', [UserController::class, 'create']);
    $app->get('/users/{id}', [UserController::class, 'getById']);
    $app->put('/users/{id}', [UserController::class, 'update']);
    $app->delete('/users/{id}', [UserController::class, 'delete']);
};
