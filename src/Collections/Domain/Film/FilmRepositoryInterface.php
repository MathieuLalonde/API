<?php
declare(strict_types=1);

namespace App\Collections\Domain\Film;

use App\Collections\DTO\Film\FilmDTO;
use App\Collections\DTO\Film\FilmListItemDTO;

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
