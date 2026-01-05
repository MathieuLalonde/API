<?php
declare(strict_types=1);

namespace App\Domain\Film;

use App\DTO\FilmDTO;
use App\DTO\FilmListItemDTO;

/**
 * Repository interface for film data access.
 */
interface FilmRepositoryInterface
{
    /**
     * Find a film by ID with all editions.
     */
    public function findById(int $id): ?FilmDTO;

    /**
     * List films with pagination and optional search.
     */
    public function list(int $limit = 50, int $offset = 0, ?string $search = null): array;

    /**
     * Count total films matching search.
     */
    public function count(?string $search = null): int;
}
