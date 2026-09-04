<?php
declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Prevents intermediate proxies (e.g. SiteGround's) from caching API responses.
 * Without this, GET responses can be served stale after deploys.
 */
class NoCacheMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);

        return $response->withHeader('Cache-Control', 'no-store');
    }
}
