<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Infrastructure\Database\PdoFactory;

/**
 * Health check endpoints.
 */
class HealthController
{
    public function __construct()
    {
    }

    public function status(Request $request, Response $response): Response
    {
        $data = [
            'status' => 'ok',
            'message' => 'API is running',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
        ];

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    public function database(Request $request, Response $response): Response
    {
        try {
            $pdo = PdoFactory::create();

            $start = microtime(true);
            $result = $pdo->query('SELECT NOW() as time')->fetch();
            $latency = round((microtime(true) - $start) * 1000, 2);

            $data = [
                'status' => 'ok',
                'database' => 'PostgreSQL',
                'latency_ms' => $latency,
                'server_time' => $result['time'] ?? null,
            ];

            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            $data = [
                'status' => 'error',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
            ];

            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(503);
        }
    }
}
