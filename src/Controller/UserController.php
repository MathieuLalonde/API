<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Service\UserService;

/**
 * User CRUD endpoints.
 */
class UserController
{
    public function __construct(private UserService $userService)
    {
    }

    public function list(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $limit = (int)($queryParams['limit'] ?? 50);
            $offset = (int)($queryParams['offset'] ?? 0);

            // Validate limits
            $limit = min(max($limit, 1), 100); // 1-100
            $offset = max($offset, 0);

            $users = $this->userService->listUsers($limit, $offset);
            $total = $this->userService->getTotalUserCount();

            $data = [
                'status' => 'ok',
                'data' => array_map(fn($user) => $user->toArray(), $users),
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => $total,
                ],
            ];

            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        try {
            $userId = (int)$args['id'];
            $user = $this->userService->getUserById($userId);

            if (!$user) {
                return $this->errorResponse($response, "User with ID {$userId} not found", 404);
            }

            $response->getBody()->write(json_encode([
                'status' => 'ok',
                'data' => $user->toArray(),
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    public function create(Request $request, Response $response): Response
    {
        try {
            $body = $request->getParsedBody();

            if (!isset($body['name'], $body['email'])) {
                return $this->errorResponse($response, "Missing required fields: name, email", 400);
            }

            $user = $this->userService->createUser($body['name'], $body['email']);

            $response->getBody()->write(json_encode([
                'status' => 'ok',
                'data' => $user->toArray(),
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($response, $e->getMessage(), 409);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $userId = (int)$args['id'];
            $body = $request->getParsedBody();

            if (!isset($body['name'], $body['email'])) {
                return $this->errorResponse($response, "Missing required fields: name, email", 400);
            }

            $user = $this->userService->updateUser($userId, $body['name'], $body['email']);

            $response->getBody()->write(json_encode([
                'status' => 'ok',
                'data' => $user->toArray(),
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $userId = (int)$args['id'];
            $deleted = $this->userService->deleteUser($userId);

            if (!$deleted) {
                return $this->errorResponse($response, "User with ID {$userId} not found", 404);
            }

            return $response->withStatus(204);
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    private function errorResponse(Response $response, string $message, int $statusCode): Response
    {
        $data = [
            'status' => 'error',
            'message' => $message,
        ];

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
}
