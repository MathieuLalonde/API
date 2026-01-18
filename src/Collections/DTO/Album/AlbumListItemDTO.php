<?php
declare(strict_types=1);

namespace App\Collections\DTO\Album;

/**
 * Lightweight DTO for album list results.
 */
class AlbumListItemDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?int $year,
        public readonly int $releaseCount
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'year' => $this->year,
            'release_count' => $this->releaseCount,
        ];
    }
}
