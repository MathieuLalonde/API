<?php
declare(strict_types=1);

namespace App\Collections\DTO;

/**
 * Data transfer object for an edition with technical details.
 */
class EditionDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $filmId,
        public readonly string $externalId,
        public readonly ?string $upc,
        public readonly ?string $releaseDate,
        public readonly ?string $mediaType,
        public readonly array $audio = [],      // array of AudioTrackDTO
        public readonly array $video = [],      // array of VideoFormatDTO
        public readonly array $discs = []       // array of EditionDiscDTO
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'film_id' => $this->filmId,
            'external_id' => $this->externalId,
            'upc' => $this->upc,
            'release_date' => $this->releaseDate,
            'media_type' => $this->mediaType,
            'audio' => array_map(fn($a) => $a->toArray(), $this->audio),
            'video' => array_map(fn($v) => $v->toArray(), $this->video),
            'discs' => array_map(fn($d) => $d->toArray(), $this->discs),
        ];
    }
}
