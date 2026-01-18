<?php
declare(strict_types=1);

namespace App\Collections\DTO\Film;

/**
 * Lightweight DTO for film list results.
 */
class FilmListItemDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?int $productionYear,
        public readonly int $editionCount
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'production_year' => $this->productionYear,
            'edition_count' => $this->editionCount,
        ];
    }
}
