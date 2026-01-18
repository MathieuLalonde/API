<?php
declare(strict_types=1);

namespace App\Collections\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Collections\Domain\Film\FilmRepositoryInterface;

/**
 * Film endpoints for listing and retrieving films.
 */
class FilmController
{
    public function __construct(private FilmRepositoryInterface $filmRepository)
    {
    }

    /**
     * List films with optional search and pagination.
     * GET /films?search=term&limit=50&offset=0
     */
    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $search = $params['search'] ?? null;
        $limit = isset($params['limit']) ? (int)$params['limit'] : 50;
        $offset = isset($params['offset']) ? (int)$params['offset'] : 0;

        // Enforce limits
        $limit = min(max($limit, 1), 100);
        $offset = max($offset, 0);

        $films = $this->filmRepository->list($limit, $offset, $search);
        $total = $this->filmRepository->count($search);

        $data = [
            'data' => array_map(fn($f) => $f->toArray(), $films),
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($films),
            ],
        ];

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get a single film by ID with all editions.
     * GET /films/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $film = $this->filmRepository->findById($id);

        if (!$film) {
            $error = ['error' => 'Film not found'];
            $response->getBody()->write(json_encode($error));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }

        $response->getBody()->write(json_encode($film->toArray()));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
