<?php
declare(strict_types=1);

namespace App\Collections\DTO;

/**
 * Edition disc label/organizational data.
 */
class EditionDiscDTO
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $discNumber,
        public readonly ?string $role,
        public readonly ?string $label,
        public readonly ?string $notes
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'disc_number' => $this->discNumber,
            'role' => $this->role,
            'label' => $this->label,
            'notes' => $this->notes,
        ];
    }
}
