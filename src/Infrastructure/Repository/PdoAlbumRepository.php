<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Collections\Domain\Album\AlbumRepositoryInterface;
use App\Collections\DTO\Album\AlbumDTO;
use App\Collections\DTO\Album\AlbumListItemDTO;
use App\Collections\DTO\Album\ReleaseDTO;
use PDO;

/**
 * PostgreSQL implementation of AlbumRepositoryInterface.
 */
class PdoAlbumRepository implements AlbumRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?AlbumDTO
    {
        // Fetch album
        $stmt = $this->pdo->prepare("
            SELECT id, master_id, title, normalized_title, year, thumb_url, cover_image_url
            FROM album
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $albumRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$albumRow) {
            return null;
        }

        // Fetch releases
        $releases = $this->fetchReleasesByAlbumId($id);

        return new AlbumDTO(
            id: (int)$albumRow['id'],
            masterId: $albumRow['master_id'] ? (int)$albumRow['master_id'] : null,
            title: $albumRow['title'],
            normalizedTitle: $albumRow['normalized_title'],
            year: $albumRow['year'] ? (int)$albumRow['year'] : null,
            thumbUrl: $albumRow['thumb_url'],
            coverImageUrl: $albumRow['cover_image_url'],
            releases: $releases
        );
    }

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
    ): array {
        $sql = "
            SELECT DISTINCT
                a.id,
                a.title,
                a.year,
                COUNT(r.id) as release_count
            FROM album a
            LEFT JOIN release r ON r.album_id = a.id
        ";

        $joins = [];
        $where = [];
        $params = [];

        // Join for filters
        if ($artist !== null) {
            $joins[] = "INNER JOIN release_artist ra ON ra.release_id = r.id";
            $where[] = "ra.artist_name ILIKE ?";
            $params[] = '%' . $artist . '%';
        }

        if ($label !== null) {
            $joins[] = "INNER JOIN release_label rl ON rl.release_id = r.id";
            $where[] = "rl.label_name ILIKE ?";
            $params[] = '%' . $label . '%';
        }

        if ($genre !== null) {
            $joins[] = "INNER JOIN release_genre rg ON rg.release_id = r.id";
            $where[] = "rg.genre ILIKE ?";
            $params[] = '%' . $genre . '%';
        }

        if ($style !== null) {
            $joins[] = "INNER JOIN release_style rs ON rs.release_id = r.id";
            $where[] = "rs.style ILIKE ?";
            $params[] = '%' . $style . '%';
        }

        if ($format !== null) {
            $joins[] = "INNER JOIN release_format rf ON rf.release_id = r.id";
            $where[] = "rf.format_name ILIKE ?";
            $params[] = '%' . $format . '%';
        }

        // Search term
        if ($search !== null) {
            $where[] = "(a.title ILIKE ? OR EXISTS (
                SELECT 1 FROM release r2
                INNER JOIN release_artist ra2 ON ra2.release_id = r2.id
                WHERE r2.album_id = a.id AND ra2.artist_name ILIKE ?
            ))";
            $searchPattern = '%' . $search . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }

        // Year filter
        if ($year !== null) {
            $where[] = "a.year = ?";
            $params[] = $year;
        }

        // Build query
        if (!empty($joins)) {
            $sql .= " " . implode(" ", array_unique($joins));
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= "
            GROUP BY a.id, a.title, a.year
            ORDER BY a.title
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function($row) {
            return new AlbumListItemDTO(
                id: (int)$row['id'],
                title: $row['title'],
                year: $row['year'] ? (int)$row['year'] : null,
                releaseCount: (int)$row['release_count']
            );
        }, $rows);
    }

    public function count(
        ?string $search = null,
        ?string $artist = null,
        ?string $label = null,
        ?string $genre = null,
        ?string $style = null,
        ?string $format = null,
        ?int $year = null
    ): int {
        $sql = "
            SELECT COUNT(DISTINCT a.id)
            FROM album a
        ";

        $joins = [];
        $where = [];
        $params = [];

        // Join for filters (same logic as list())
        if ($artist !== null) {
            $joins[] = "INNER JOIN release r_artist ON r_artist.album_id = a.id";
            $joins[] = "INNER JOIN release_artist ra ON ra.release_id = r_artist.id";
            $where[] = "ra.artist_name ILIKE ?";
            $params[] = '%' . $artist . '%';
        }

        if ($label !== null) {
            if (!in_array("INNER JOIN release r_label ON r_label.album_id = a.id", $joins)) {
                $joins[] = "INNER JOIN release r_label ON r_label.album_id = a.id";
            }
            $joins[] = "INNER JOIN release_label rl ON rl.release_id = r_label.id";
            $where[] = "rl.label_name ILIKE ?";
            $params[] = '%' . $label . '%';
        }

        if ($genre !== null) {
            if (!in_array("INNER JOIN release r_genre ON r_genre.album_id = a.id", $joins)) {
                $joins[] = "INNER JOIN release r_genre ON r_genre.album_id = a.id";
            }
            $joins[] = "INNER JOIN release_genre rg ON rg.release_id = r_genre.id";
            $where[] = "rg.genre ILIKE ?";
            $params[] = '%' . $genre . '%';
        }

        if ($style !== null) {
            if (!in_array("INNER JOIN release r_style ON r_style.album_id = a.id", $joins)) {
                $joins[] = "INNER JOIN release r_style ON r_style.album_id = a.id";
            }
            $joins[] = "INNER JOIN release_style rs ON rs.release_id = r_style.id";
            $where[] = "rs.style ILIKE ?";
            $params[] = '%' . $style . '%';
        }

        if ($format !== null) {
            if (!in_array("INNER JOIN release r_format ON r_format.album_id = a.id", $joins)) {
                $joins[] = "INNER JOIN release r_format ON r_format.album_id = a.id";
            }
            $joins[] = "INNER JOIN release_format rf ON rf.release_id = r_format.id";
            $where[] = "rf.format_name ILIKE ?";
            $params[] = '%' . $format . '%';
        }

        // Search term
        if ($search !== null) {
            $where[] = "(a.title ILIKE ? OR EXISTS (
                SELECT 1 FROM release r2
                INNER JOIN release_artist ra2 ON ra2.release_id = r2.id
                WHERE r2.album_id = a.id AND ra2.artist_name ILIKE ?
            ))";
            $searchPattern = '%' . $search . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }

        // Year filter
        if ($year !== null) {
            $where[] = "a.year = ?";
            $params[] = $year;
        }

        // Build query
        if (!empty($joins)) {
            $sql .= " " . implode(" ", array_unique($joins));
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private function fetchReleasesByAlbumId(int $albumId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, album_id, discogs_id, discogs_instance_id, date_added, rating,
                   media_condition, sleeve_condition, resource_url
            FROM release
            WHERE album_id = ?
            ORDER BY date_added DESC, id
        ");
        $stmt->execute([$albumId]);
        $releaseRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $releases = [];
        foreach ($releaseRows as $row) {
            $releaseId = (int)$row['id'];

            $releases[] = new ReleaseDTO(
                id: $releaseId,
                albumId: (int)$row['album_id'],
                discogsId: (int)$row['discogs_id'],
                discogsInstanceId: (int)$row['discogs_instance_id'],
                dateAdded: $row['date_added'],
                rating: $row['rating'] ? (int)$row['rating'] : null,
                mediaCondition: $row['media_condition'],
                sleeveCondition: $row['sleeve_condition'],
                resourceUrl: $row['resource_url'],
                artists: $this->fetchReleaseArtists($releaseId),
                labels: $this->fetchReleaseLabels($releaseId),
                genres: $this->fetchReleaseGenres($releaseId),
                styles: $this->fetchReleaseStyles($releaseId),
                formats: $this->fetchReleaseFormats($releaseId)
            );
        }

        return $releases;
    }

    private function fetchReleaseArtists(int $releaseId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT artist_name
            FROM release_artist
            WHERE release_id = ?
            ORDER BY sequence
        ");
        $stmt->execute([$releaseId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => $row['artist_name'], $rows);
    }

    private function fetchReleaseLabels(int $releaseId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT label_name, catalog_no
            FROM release_label
            WHERE release_id = ?
            ORDER BY sequence
        ");
        $stmt->execute([$releaseId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => [
            'name' => $row['label_name'],
            'catalog_no' => $row['catalog_no'],
        ], $rows);
    }

    private function fetchReleaseGenres(int $releaseId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT genre
            FROM release_genre
            WHERE release_id = ?
            ORDER BY genre
        ");
        $stmt->execute([$releaseId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => $row['genre'], $rows);
    }

    private function fetchReleaseStyles(int $releaseId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT style
            FROM release_style
            WHERE release_id = ?
            ORDER BY style
        ");
        $stmt->execute([$releaseId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => $row['style'], $rows);
    }

    private function fetchReleaseFormats(int $releaseId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT format_name, quantity, descriptions
            FROM release_format
            WHERE release_id = ?
            ORDER BY sequence
        ");
        $stmt->execute([$releaseId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => [
            'name' => $row['format_name'],
            'quantity' => $row['quantity'],
            'descriptions' => $row['descriptions'],
        ], $rows);
    }
}
