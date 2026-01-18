<?php
declare(strict_types=1);

namespace App\Collections\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Collections\Domain\Album\AlbumRepositoryInterface;

/**
 * Album endpoints for listing and retrieving albums.
 */
class AlbumController
{
    public function __construct(private AlbumRepositoryInterface $albumRepository)
    {
    }

    /**
     * List albums with optional search, filters, and pagination.
     * GET /albums?search=term&artist=name&label=name&genre=name&style=name&format=name&year=2020&limit=50&offset=0
     */
    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $search = $params['search'] ?? null;
        $artist = $params['artist'] ?? null;
        $label = $params['label'] ?? null;
        $genre = $params['genre'] ?? null;
        $style = $params['style'] ?? null;
        $format = $params['format'] ?? null;
        $year = isset($params['year']) ? (int)$params['year'] : null;
        $limit = isset($params['limit']) ? (int)$params['limit'] : 50;
        $offset = isset($params['offset']) ? (int)$params['offset'] : 0;

        // Enforce limits
        $limit = min(max($limit, 1), 100);
        $offset = max($offset, 0);

        $albums = $this->albumRepository->list($limit, $offset, $search, $artist, $label, $genre, $style, $format, $year);
        $total = $this->albumRepository->count($search, $artist, $label, $genre, $style, $format, $year);

        $data = [
            'data' => array_map(fn($a) => $a->toArray(), $albums),
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($albums),
            ],
        ];

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get a single album by ID with all releases.
     * GET /albums/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $album = $this->albumRepository->findById($id);

        if (!$album) {
            $error = ['error' => 'Album not found'];
            $response->getBody()->write(json_encode($error));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }

        $response->getBody()->write(json_encode($album->toArray()));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
