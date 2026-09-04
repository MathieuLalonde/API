<?php
declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * CORS middleware to allow cross-origin requests from the frontend.
 */
class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Get the origin from the request
        $origin = $request->getHeaderLine('Origin');

        // In development, allow localhost origins; in production, restrict to your domain
        $allowedOrigins = [
            'http://localhost:5173',
            'http://localhost:5174',
            'http://127.0.0.1:5173',
            'http://127.0.0.1:5174',
        ];

        // Determine which origin to use
        if (empty($origin) || in_array($origin, $allowedOrigins, true)) {
            $originToUse = !empty($origin) ? $origin : '*';
        } else {
            // In production, you might want to validate against a whitelist
            $originToUse = $origin;
        }

        // Handle preflight OPTIONS requests before processing
        if ($request->getMethod() === 'OPTIONS') {
            $response = new \Slim\Psr7\Response();
            return $response
                ->withHeader('Access-Control-Allow-Origin', $originToUse)
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
                ->withHeader('Access-Control-Max-Age', '3600')
                ->withStatus(204);
        }

        // Process the request
        $response = $handler->handle($request);

        // Add CORS headers to the response
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $originToUse)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Max-Age', '3600');

        return $response;
    }
}
