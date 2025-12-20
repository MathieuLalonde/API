<?php
declare(strict_types=1);

namespace App\DTO;

/**
 * User Data Transfer Object.
 */
class UserDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?\DateTime $createdAt = null,
    ) {
    }

    /**
     * Convert to array for JSON responses.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
        ];
    }
}
