<?php
declare(strict_types=1);

namespace App\Collections\Domain\Album;

use App\Collections\DTO\Album\AlbumDTO;
use App\Collections\DTO\Album\AlbumListItemDTO;

/**
 * Repository interface for album data access.
 */
interface AlbumRepositoryInterface
{
    /**
     * Find an album by ID with all releases.
     */
    public function findById(int $id): ?AlbumDTO;

    /**
     * List albums with pagination and optional search/filtering.
     *
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @param string|null $search Search term (title, artist)
     * @param string|null $artist Filter by artist name
     * @param string|null $label Filter by label name
     * @param string|null $genre Filter by genre
     * @param string|null $style Filter by style
     * @param string|null $format Filter by format
     * @param int|null $year Filter by year
     * @return array<AlbumListItemDTO>
     */
    public function list(
        int $limit = 50,
        int $offset = 0,
        ?string $search = null,
        ?string $artist = null,
        ?string $label = null,
        ?string $genre = null,
        ?string $style = null,
        ?string $format = null,
        ?int $year = null
    ): array;

    /**
     * Count total albums matching search/filters.
     */
    public function count(
        ?string $search = null,
        ?string $artist = null,
        ?string $label = null,
        ?string $genre = null,
        ?string $style = null,
        ?string $format = null,
        ?int $year = null
    ): int;
}
