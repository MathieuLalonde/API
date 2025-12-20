<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\User\UserRepositoryInterface;
use App\DTO\UserDTO;
use App\Mapper\UserMapper;
use PDO;

/**
 * PostgreSQL implementation of UserRepositoryInterface using PDO.
 */
class PdoUserRepository implements UserRepositoryInterface
{
    private const TABLE = 'users';

    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?UserDTO
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM " . self::TABLE . " WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? UserMapper::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?UserDTO
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM " . self::TABLE . " WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        return $row ? UserMapper::fromRow($row) : null;
    }

    public function list(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM " . self::TABLE . " ORDER BY created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => UserMapper::fromRow($row), $rows);
    }

    public function create(string $name, string $email): UserDTO
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO " . self::TABLE . " (name, email, created_at) 
             VALUES (?, ?, NOW()) 
             RETURNING id, name, email, created_at"
        );
        $stmt->execute([$name, $email]);
        $row = $stmt->fetch();

        return UserMapper::fromRow($row);
    }

    public function update(int $id, string $name, string $email): UserDTO
    {
        $stmt = $this->pdo->prepare(
            "UPDATE " . self::TABLE . " 
             SET name = ?, email = ? 
             WHERE id = ?
             RETURNING id, name, email, created_at"
        );
        $stmt->execute([$name, $email, $id]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new \RuntimeException("User with ID {$id} not found");
        }

        return UserMapper::fromRow($row);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM " . self::TABLE . " WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function count(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM " . self::TABLE);
        return (int)$stmt->fetchColumn();
    }
}
