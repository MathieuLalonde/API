<?php
declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

// Test endpoint to verify deployment
$app->get('/', function (Request $request, Response $response) {
    $data = [
        'status' => 'ok',
        'message' => 'API is running',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '1.0.0'
    ];
    
    $response->getBody()->write(json_encode($data));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

// Health check endpoint
$app->get('/health', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode(['status' => 'healthy']));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

$app->run();
