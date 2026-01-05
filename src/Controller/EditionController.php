<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Infrastructure\Repository\PdoEditionRepository;

/**
 * Edition endpoints for retrieving edition technical details.
 */
class EditionController
{
    public function __construct(private PdoEditionRepository $editionRepository)
    {
    }

    /**
     * Get a single edition by ID with audio, video, and disc details.
     * GET /editions/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $edition = $this->editionRepository->findById($id);

        if (!$edition) {
            $error = ['error' => 'Edition not found'];
            $response->getBody()->write(json_encode($error));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }

        $response->getBody()->write(json_encode($edition->toArray()));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
