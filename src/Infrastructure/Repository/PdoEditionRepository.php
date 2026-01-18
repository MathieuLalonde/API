<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Collections\DTO\EditionDTO;
use App\Collections\DTO\AudioTrackDTO;
use App\Collections\DTO\VideoFormatDTO;
use App\Collections\DTO\EditionDiscDTO;
use PDO;

/**
 * Repository for edition-specific queries.
 */
class PdoEditionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Find edition by ID with all technical details.
     */
    public function findById(int $id): ?EditionDTO
    {
        $stmt = $this->pdo->prepare("
            SELECT id, film_id, external_id, upc, release_date, media_type
            FROM edition
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new EditionDTO(
            id: (int)$row['id'],
            filmId: (int)$row['film_id'],
            externalId: $row['external_id'],
            upc: $row['upc'],
            releaseDate: $row['release_date'],
            mediaType: $row['media_type'],
            audio: $this->fetchAudioTracks($id),
            video: $this->fetchVideoFormats($id),
            discs: $this->fetchEditionDiscs($id)
        );
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
