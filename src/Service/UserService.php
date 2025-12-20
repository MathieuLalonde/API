<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\User\UserRepositoryInterface;
use App\DTO\UserDTO;

/**
 * User business logic service.
 * Orchestrates repository and business rules.
 */
class UserService
{
    public function __construct(private UserRepositoryInterface $repository)
    {
    }

    public function getUserById(int $id): ?UserDTO
    {
        return $this->repository->findById($id);
    }

    public function getUserByEmail(string $email): ?UserDTO
    {
        return $this->repository->findByEmail($email);
    }

    public function listUsers(int $limit = 50, int $offset = 0): array
    {
        return $this->repository->list($limit, $offset);
    }

    public function createUser(string $name, string $email): UserDTO
    {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email format: {$email}");
        }

        // Check if email already exists
        if ($this->repository->findByEmail($email)) {
            throw new \RuntimeException("User with email {$email} already exists");
        }

        return $this->repository->create($name, $email);
    }

    public function updateUser(int $id, string $name, string $email): UserDTO
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email format: {$email}");
        }

        return $this->repository->update($id, $name, $email);
    }

    public function deleteUser(int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function getTotalUserCount(): int
    {
        return $this->repository->count();
    }
}
