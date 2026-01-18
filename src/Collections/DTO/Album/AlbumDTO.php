<?php
declare(strict_types=1);

namespace App\Collections\DTO\Album;

/**
 * Data transfer object for an album with full details.
 */
class AlbumDTO
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $masterId,
        public readonly string $title,
        public readonly ?string $normalizedTitle,
        public readonly ?int $year,
        public readonly ?string $thumbUrl,
        public readonly ?string $coverImageUrl,
        public readonly array $releases = []  // array of ReleaseDTO
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'master_id' => $this->masterId,
            'title' => $this->title,
            'normalized_title' => $this->normalizedTitle,
            'year' => $this->year,
            'thumb_url' => $this->thumbUrl,
            'cover_image_url' => $this->coverImageUrl,
            'releases' => array_map(fn($r) => $r->toArray(), $this->releases),
        ];
    }
}
