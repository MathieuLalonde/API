<?php
declare(strict_types=1);

namespace App\Infrastructure\Database;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Container\ContainerInterface;
use PDO;

/**
 * Middleware that sets PostgreSQL search_path based on route prefix.
 * This allows different projects to use different schemas in the same database.
 */
class SchemaMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ContainerInterface $container,
        private string $schema
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $pdo = $this->container->get(PDO::class);
        $pdo->exec("SET search_path TO {$this->schema}");

        return $handler->handle($request);
    }
}
