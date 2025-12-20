<?php
declare(strict_types=1);

namespace App\Domain\User;

use App\DTO\UserDTO;

/**
 * User repository interface - database agnostic contract.
 * Allows swapping PDO, Doctrine DBAL, or ORM implementations.
 */
interface UserRepositoryInterface
{
    /**
     * Find a user by ID.
     */
    public function findById(int $id): ?UserDTO;

    /**
     * Find a user by email.
     */
    public function findByEmail(string $email): ?UserDTO;

    /**
     * Get all users.
     */
    public function list(int $limit = 50, int $offset = 0): array;

    /**
     * Create a new user.
     */
    public function create(string $name, string $email): UserDTO;

    /**
     * Update a user.
     */
    public function update(int $id, string $name, string $email): UserDTO;

    /**
     * Delete a user.
     */
    public function delete(int $id): bool;

    /**
     * Count total users.
     */
    public function count(): int;
}
