<?php
declare(strict_types=1);

namespace App\Collections\Infrastructure\Import;

/**
 * Utility class for normalizing film titles for matching.
 */
class TitleNormalizer
{
    /**
     * Normalize a film title for matching purposes.
     * Removes accents, leading articles, punctuation, and normalizes whitespace.
     */
    public static function normalize(?string $title): ?string
    {
        if (!$title) {
            return null;
        }

        $title = mb_strtolower($title, 'UTF-8');

        // Remove accents
        $title = iconv('UTF-8', 'ASCII//TRANSLIT', $title);

        // Remove leading articles
        $title = preg_replace('/^(the|a|an|le|la|les|l\')\s+/i', '', $title);

        // Remove punctuation
        $title = preg_replace('/[^a-z0-9\s]/', '', $title);

        // Collapse whitespace
        $title = preg_replace('/\s+/', ' ', trim($title));

        return $title;
    }
}
