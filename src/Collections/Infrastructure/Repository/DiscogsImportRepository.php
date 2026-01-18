<?php
declare(strict_types=1);

namespace App\Collections\Infrastructure\Repository;

use PDO;
use PDOStatement;

/**
 * Repository for Discogs LP import operations.
 * Manages all database operations for importing albums, releases, and relationships.
 */
class DiscogsImportRepository
{
    private PDOStatement $findAlbumByMasterIdStmt;
    private PDOStatement $insertAlbumStmt;
    private PDOStatement $updateAlbumStmt;
    private PDOStatement $releaseUpsertStmt;
    private PDOStatement $deleteReleaseArtistStmt;
    private PDOStatement $insertReleaseArtistStmt;
    private PDOStatement $deleteReleaseLabelStmt;
    private PDOStatement $insertReleaseLabelStmt;
    private PDOStatement $deleteReleaseGenreStmt;
    private PDOStatement $insertReleaseGenreStmt;
    private PDOStatement $deleteReleaseStyleStmt;
    private PDOStatement $insertReleaseStyleStmt;
    private PDOStatement $deleteReleaseFormatStmt;
    private PDOStatement $insertReleaseFormatStmt;

    public function __construct(private PDO $pdo)
    {
        // Set search_path to collections schema
        $pdo->exec("SET search_path TO collections");
        $pdo->exec("SET client_encoding TO 'UTF8'");

        // Initialize prepared statements
        $this->findAlbumByMasterIdStmt = $pdo->prepare("
            SELECT id, master_id, title, normalized_title, year, thumb_url, cover_image_url
            FROM album
            WHERE master_id = :master_id
            LIMIT 1
        ");

        $this->insertAlbumStmt = $pdo->prepare("
            INSERT INTO album (master_id, title, normalized_title, year, thumb_url, cover_image_url)
            VALUES (:master_id, :title, :normalized_title, :year, :thumb_url, :cover_image_url)
            RETURNING id
        ");

        $this->updateAlbumStmt = $pdo->prepare("
            UPDATE album
            SET title = :title,
                normalized_title = :normalized_title,
                year = :year,
                thumb_url = :thumb_url,
                cover_image_url = :cover_image_url
            WHERE id = :album_id
        ");

        $this->releaseUpsertStmt = $pdo->prepare("
            INSERT INTO release (
                album_id, discogs_id, discogs_instance_id, date_added, rating,
                media_condition, sleeve_condition, resource_url
            )
            VALUES (
                :album_id, :discogs_id, :discogs_instance_id, :date_added, :rating,
                :media_condition, :sleeve_condition, :resource_url
            )
            ON CONFLICT (discogs_instance_id) DO UPDATE SET
                album_id = EXCLUDED.album_id,
                discogs_id = EXCLUDED.discogs_id,
                date_added = EXCLUDED.date_added,
                rating = EXCLUDED.rating,
                media_condition = EXCLUDED.media_condition,
                sleeve_condition = EXCLUDED.sleeve_condition,
                resource_url = EXCLUDED.resource_url
            RETURNING id
        ");

        $this->deleteReleaseArtistStmt = $pdo->prepare("
            DELETE FROM release_artist WHERE release_id = ?
        ");

        $this->insertReleaseArtistStmt = $pdo->prepare("
            INSERT INTO release_artist (release_id, artist_name, sequence)
            VALUES (?, ?, ?)
            ON CONFLICT DO NOTHING
        ");

        $this->deleteReleaseLabelStmt = $pdo->prepare("
            DELETE FROM release_label WHERE release_id = ?
        ");

        $this->insertReleaseLabelStmt = $pdo->prepare("
            INSERT INTO release_label (release_id, label_name, catalog_no, sequence)
            VALUES (?, ?, ?, ?)
            ON CONFLICT DO NOTHING
        ");

        $this->deleteReleaseGenreStmt = $pdo->prepare("
            DELETE FROM release_genre WHERE release_id = ?
        ");

        $this->insertReleaseGenreStmt = $pdo->prepare("
            INSERT INTO release_genre (release_id, genre)
            VALUES (?, ?)
            ON CONFLICT DO NOTHING
        ");

        $this->deleteReleaseStyleStmt = $pdo->prepare("
            DELETE FROM release_style WHERE release_id = ?
        ");

        $this->insertReleaseStyleStmt = $pdo->prepare("
            INSERT INTO release_style (release_id, style)
            VALUES (?, ?)
            ON CONFLICT DO NOTHING
        ");

        $this->deleteReleaseFormatStmt = $pdo->prepare("
            DELETE FROM release_format WHERE release_id = ?
        ");

        $this->insertReleaseFormatStmt = $pdo->prepare("
            INSERT INTO release_format (release_id, format_name, quantity, descriptions, sequence)
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT DO NOTHING
        ");
    }

    /**
     * Find album by Discogs master_id.
     *
     * @param int|null $masterId Discogs master_id (null if no master)
     * @return array<string|int|null>|null Album data or null if not found
     */
    public function findAlbumByMasterId(?int $masterId): ?array
    {
        if ($masterId === null) {
            return null;
        }

        $this->findAlbumByMasterIdStmt->execute(['master_id' => $masterId]);
        $result = $this->findAlbumByMasterIdStmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Create a new album.
     *
     * @param array<string|int|null> $data Album data
     * @return int Created album ID
     */
    public function createAlbum(array $data): int
    {
        $this->insertAlbumStmt->execute($data);
        return (int)$this->insertAlbumStmt->fetchColumn();
    }

    /**
     * Update an album.
     *
     * @param int $albumId Album ID
     * @param array<string|int|null> $data Album data
     */
    public function updateAlbum(int $albumId, array $data): void
    {
        $this->updateAlbumStmt->execute([
            'album_id' => $albumId,
            ...$data,
        ]);
    }

    /**
     * Upsert a release (insert or update by discogs_instance_id).
     *
     * @param array<string|int|null> $data Release data
     * @return int Release ID
     */
    public function upsertRelease(array $data): int
    {
        $this->releaseUpsertStmt->execute($data);
        return (int)$this->releaseUpsertStmt->fetchColumn();
    }

    /**
     * Load all releases into cache for fast lookup.
     * Returns array mapping discogs_instance_id => release data
     *
     * @return array<int, array{id: int, discogs_id: int}>
     */
    public function loadReleaseCache(): array
    {
        $stmt = $this->pdo->query("
            SELECT discogs_instance_id, id, discogs_id
            FROM release
        ");

        $cache = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cache[(int)$row['discogs_instance_id']] = [
                'id' => (int)$row['id'],
                'discogs_id' => (int)$row['discogs_id'],
            ];
        }

        return $cache;
    }

    /**
     * Sync release artists (delete existing, insert new).
     *
     * @param int $releaseId Release ID
     * @param array<string> $artists Artist names
     */
    public function syncReleaseArtists(int $releaseId, array $artists): void
    {
        $this->deleteReleaseArtistStmt->execute([$releaseId]);

        $validArtists = array_filter(array_map('trim', $artists));
        if (empty($validArtists)) {
            return;
        }

        foreach ($validArtists as $sequence => $artist) {
            $this->insertReleaseArtistStmt->execute([
                $releaseId,
                $artist,
                $sequence + 1,
            ]);
        }
    }

    /**
     * Sync release labels (delete existing, insert new).
     *
     * @param int $releaseId Release ID
     * @param array<array{name: string, catalog_no?: string|null}> $labels Label data
     */
    public function syncReleaseLabels(int $releaseId, array $labels): void
    {
        $this->deleteReleaseLabelStmt->execute([$releaseId]);

        if (empty($labels)) {
            return;
        }

        foreach ($labels as $sequence => $label) {
            $this->insertReleaseLabelStmt->execute([
                $releaseId,
                $label['name'],
                $label['catalog_no'] ?? null,
                $sequence + 1,
            ]);
        }
    }

    /**
     * Sync release genres (delete existing, insert new).
     *
     * @param int $releaseId Release ID
     * @param array<string> $genres Genre names
     */
    public function syncReleaseGenres(int $releaseId, array $genres): void
    {
        $this->deleteReleaseGenreStmt->execute([$releaseId]);

        $validGenres = array_filter(array_map('trim', $genres));
        if (empty($validGenres)) {
            return;
        }

        foreach ($validGenres as $genre) {
            $this->insertReleaseGenreStmt->execute([$releaseId, $genre]);
        }
    }

    /**
     * Sync release styles (delete existing, insert new).
     *
     * @param int $releaseId Release ID
     * @param array<string> $styles Style names
     */
    public function syncReleaseStyles(int $releaseId, array $styles): void
    {
        $this->deleteReleaseStyleStmt->execute([$releaseId]);

        $validStyles = array_filter(array_map('trim', $styles));
        if (empty($validStyles)) {
            return;
        }

        foreach ($validStyles as $style) {
            $this->insertReleaseStyleStmt->execute([$releaseId, $style]);
        }
    }

    /**
     * Sync release formats (delete existing, insert new).
     *
     * @param int $releaseId Release ID
     * @param array<array{name: string, quantity?: string|null, descriptions?: string|null}> $formats Format data
     */
    public function syncReleaseFormats(int $releaseId, array $formats): void
    {
        $this->deleteReleaseFormatStmt->execute([$releaseId]);

        if (empty($formats)) {
            return;
        }

        foreach ($formats as $sequence => $format) {
            $this->insertReleaseFormatStmt->execute([
                $releaseId,
                $format['name'],
                $format['quantity'] ?? null,
                $format['descriptions'] ?? null,
                $sequence + 1,
            ]);
        }
    }

    /**
     * Delete orphaned releases (releases not in the provided instance IDs list).
     *
     * @param array<int> $importedInstanceIds List of instance IDs to keep
     */
    public function deleteOrphanedReleases(array $importedInstanceIds): void
    {
        if (empty($importedInstanceIds)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($importedInstanceIds), '?'));
        $stmt = $this->pdo->prepare("
            DELETE FROM release
            WHERE discogs_instance_id NOT IN ($placeholders)
        ");
        $stmt->execute($importedInstanceIds);
    }

    /**
     * Delete orphaned albums (albums with no releases).
     */
    public function deleteOrphanedAlbums(): void
    {
        $this->pdo->exec("
            DELETE FROM album
            WHERE id NOT IN (
                SELECT DISTINCT album_id FROM release
            )
        ");
    }
}
