<?php
declare(strict_types=1);

namespace App\Collections\DTO;

/**
 * Data transfer object for a film with full details.
 */
class FilmDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $sortTitle,
        public readonly ?string $originalTitle,
        public readonly ?string $normalizedTitle,
        public readonly ?int $productionYear,
        public readonly ?int $runningTimeMin,
        public readonly array $editions = []  // array of EditionDTO
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'sort_title' => $this->sortTitle,
            'original_title' => $this->originalTitle,
            'production_year' => $this->productionYear,
            'running_time_min' => $this->runningTimeMin,
            'editions' => array_map(fn($e) => $e->toArray(), $this->editions),
        ];
    }
}
