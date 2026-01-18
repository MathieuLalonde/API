<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Collections\Domain\Film\FilmRepositoryInterface;
use App\Collections\DTO\Film\FilmDTO;
use App\Collections\DTO\Film\FilmListItemDTO;
use App\Collections\DTO\Film\EditionDTO;
use App\Collections\DTO\Film\AudioTrackDTO;
use App\Collections\DTO\Film\VideoFormatDTO;
use App\Collections\DTO\Film\EditionDiscDTO;
use PDO;

/**
 * PostgreSQL implementation of FilmRepositoryInterface.
 */
class PdoFilmRepository implements FilmRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?FilmDTO
    {
        // Fetch film
        $stmt = $this->pdo->prepare("
            SELECT id, title, sort_title, original_title, normalized_title, 
                   production_year, running_time_min
            FROM film
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $filmRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$filmRow) {
            return null;
        }

        // Fetch editions
        $editions = $this->fetchEditionsByFilmId($id);

        return new FilmDTO(
            id: (int)$filmRow['id'],
            title: $filmRow['title'],
            sortTitle: $filmRow['sort_title'],
            originalTitle: $filmRow['original_title'],
            normalizedTitle: $filmRow['normalized_title'],
            productionYear: $filmRow['production_year'] ? (int)$filmRow['production_year'] : null,
            runningTimeMin: $filmRow['running_time_min'] ? (int)$filmRow['running_time_min'] : null,
            editions: $editions
        );
    }

    public function list(int $limit = 50, int $offset = 0, ?string $search = null): array
    {
        $sql = "
            SELECT 
                f.id, 
                f.title, 
                f.production_year,
                COUNT(e.id) as edition_count
            FROM film f
            LEFT JOIN edition e ON e.film_id = f.id
        ";

        $params = [];

        if ($search) {
            $sql .= " WHERE f.title ILIKE ? OR f.original_title ILIKE ?";
            $searchPattern = '%' . $search . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }

        $sql .= "
            GROUP BY f.id, f.title, f.production_year
            ORDER BY f.title
            LIMIT ? OFFSET ?
        ";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function($row) {
            return new FilmListItemDTO(
                id: (int)$row['id'],
                title: $row['title'],
                productionYear: $row['production_year'] ? (int)$row['production_year'] : null,
                editionCount: (int)$row['edition_count']
            );
        }, $rows);
    }

    public function count(?string $search = null): int
    {
        $sql = "SELECT COUNT(*) FROM film";
        $params = [];

        if ($search) {
            $sql .= " WHERE title ILIKE ? OR original_title ILIKE ?";
            $searchPattern = '%' . $search . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private function fetchEditionsByFilmId(int $filmId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, film_id, external_id, upc, release_date, media_type
            FROM edition
            WHERE film_id = ?
            ORDER BY release_date, id
        ");
        $stmt->execute([$filmId]);
        $editionRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $editions = [];
        foreach ($editionRows as $row) {
            $editionId = (int)$row['id'];

            $editions[] = new EditionDTO(
                id: $editionId,
                filmId: (int)$row['film_id'],
                externalId: $row['external_id'],
                upc: $row['upc'],
                releaseDate: $row['release_date'],
                mediaType: $row['media_type'],
                audio: $this->fetchAudioTracks($editionId),
                video: $this->fetchVideoFormats($editionId),
                discs: $this->fetchEditionDiscs($editionId)
            );
        }

        return $editions;
    }

    private function fetchAudioTracks(int $editionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, language, channel_layout, format, is_descriptive
            FROM audio_track
            WHERE edition_id = ?
            ORDER BY id
        ");
        $stmt->execute([$editionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => new AudioTrackDTO(
            id: (int)$row['id'],
            language: $row['language'],
            channelLayout: $row['channel_layout'],
            format: $row['format'],
            isDescriptive: (bool)$row['is_descriptive']
        ), $rows);
    }

    private function fetchVideoFormats(int $editionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT edition_id, is_color, is_black_and_white, is_colorized, is_mixed_color,
                   is_2d, is_3d_anaglyph, is_3d_bluray,
                   is_16x9, aspect_ratio, is_full_frame, is_letterbox, is_pan_and_scan,
                   is_dual_layered, is_dual_sided, video_standard
            FROM video_format
            WHERE edition_id = ?
        ");
        $stmt->execute([$editionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => new VideoFormatDTO(
            editionId: (int)$row['edition_id'],
            isColor: (bool)$row['is_color'],
            isBlackAndWhite: (bool)$row['is_black_and_white'],
            isColorized: (bool)$row['is_colorized'],
            isMixedColor: (bool)$row['is_mixed_color'],
            is2d: (bool)$row['is_2d'],
            is3dAnaglyph: (bool)$row['is_3d_anaglyph'],
            is3dBluray: (bool)$row['is_3d_bluray'],
            is16x9: (bool)$row['is_16x9'],
            aspectRatio: $row['aspect_ratio'] ? (float)$row['aspect_ratio'] : null,
            isFullFrame: (bool)$row['is_full_frame'],
            isLetterbox: (bool)$row['is_letterbox'],
            isPanAndScan: (bool)$row['is_pan_and_scan'],
            isDualLayered: (bool)$row['is_dual_layered'],
            isDualSided: (bool)$row['is_dual_sided'],
            videoStandard: $row['video_standard']
        ), $rows);
    }

    private function fetchEditionDiscs(int $editionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, disc_number, role, label, notes
            FROM edition_disc
            WHERE edition_id = ?
            ORDER BY disc_number, id
        ");
        $stmt->execute([$editionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => new EditionDiscDTO(
            id: (int)$row['id'],
            discNumber: $row['disc_number'] ? (int)$row['disc_number'] : null,
            role: $row['role'],
            label: $row['label'],
            notes: $row['notes']
        ), $rows);
    }
}
