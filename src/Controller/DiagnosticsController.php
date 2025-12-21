<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DiagnosticsController
{
    public function phpVersion(Request $request, Response $response): Response
    {
        $version = phpversion();
        $response->getBody()->write($version);
        return $response->withHeader('Content-Type', 'text/plain');
    }
}
