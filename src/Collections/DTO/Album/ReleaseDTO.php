<?php
declare(strict_types=1);

namespace App\Collections\DTO\Album;

/**
 * Data transfer object for a release (physical LP edition).
 */
class ReleaseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $albumId,
        public readonly int $discogsId,
        public readonly int $discogsInstanceId,
        public readonly ?string $dateAdded,
        public readonly ?int $rating,
        public readonly ?string $mediaCondition,
        public readonly ?string $sleeveCondition,
        public readonly ?string $resourceUrl,
        public readonly array $artists = [],    // array of strings
        public readonly array $labels = [],     // array of ['name' => string, 'catalog_no' => ?string]
        public readonly array $genres = [],     // array of strings
        public readonly array $styles = [],     // array of strings
        public readonly array $formats = []     // array of ['name' => string, 'quantity' => ?string, 'descriptions' => ?string]
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'album_id' => $this->albumId,
            'discogs_id' => $this->discogsId,
            'discogs_instance_id' => $this->discogsInstanceId,
            'date_added' => $this->dateAdded,
            'rating' => $this->rating,
            'media_condition' => $this->mediaCondition,
            'sleeve_condition' => $this->sleeveCondition,
            'resource_url' => $this->resourceUrl,
            'artists' => $this->artists,
            'labels' => $this->labels,
            'genres' => $this->genres,
            'styles' => $this->styles,
            'formats' => $this->formats,
        ];
    }
}
