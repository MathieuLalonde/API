<?php
declare(strict_types=1);

namespace App\Mapper;

use App\DTO\UserDTO;

/**
 * Maps between database rows and UserDTO.
 */
class UserMapper
{
    /**
     * Convert database row to UserDTO.
     */
    public static function fromRow(array $row): UserDTO
    {
        return new UserDTO(
            id: (int)$row['id'],
            name: $row['name'],
            email: $row['email'],
            createdAt: $row['created_at'] ? new \DateTime($row['created_at']) : null,
        );
    }

    /**
     * Convert UserDTO to array (for responses).
     */
    public static function toArray(UserDTO $user): array
    {
        return $user->toArray();
    }
}
