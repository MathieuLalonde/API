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

    /**
     * Normalize season numbers in sort titles.
     * Converts written numbers to digits:
     * - "Season One" → "Season 1"
     * - "Second Season" → "2nd Season"
     * - Up to "Twenty" / "Twentieth"
     */
    public static function normalizeSeasonNumbers(?string $sortTitle): ?string
    {
        if (!$sortTitle) {
            return null;
        }

        // Mapping of written numbers to digits (1-20)
        $numberMap = [
            'One' => '1',
            'Two' => '2',
            'Three' => '3',
            'Four' => '4',
            'Five' => '5',
            'Six' => '6',
            'Seven' => '7',
            'Eight' => '8',
            'Nine' => '9',
            'Ten' => '10',
            'Eleven' => '11',
            'Twelve' => '12',
            'Thirteen' => '13',
            'Fourteen' => '14',
            'Fifteen' => '15',
            'Sixteen' => '16',
            'Seventeen' => '17',
            'Eighteen' => '18',
            'Nineteen' => '19',
            'Twenty' => '20',
        ];

        // Mapping of ordinals to numeric ordinals (First-20th)
        $ordinalMap = [
            'First' => '1st',
            'Second' => '2nd',
            'Third' => '3rd',
            'Fourth' => '4th',
            'Fifth' => '5th',
            'Sixth' => '6th',
            'Seventh' => '7th',
            'Eighth' => '8th',
            'Ninth' => '9th',
            'Tenth' => '10th',
            'Eleventh' => '11th',
            'Twelfth' => '12th',
            'Thirteenth' => '13th',
            'Fourteenth' => '14th',
            'Fifteenth' => '15th',
            'Sixteenth' => '16th',
            'Seventeenth' => '17th',
            'Eighteenth' => '18th',
            'Nineteenth' => '19th',
            'Twentieth' => '20th',
        ];

        $result = $sortTitle;

        // Replace "Season [Number]" patterns (e.g., "Season One" → "Season 1")
        foreach ($numberMap as $written => $digit) {
            $result = preg_replace('/\bSeason\s+' . preg_quote($written, '/') . '\b/i', "Season {$digit}", $result);
        }

        // Replace "[Ordinal] Season" patterns (e.g., "Second Season" → "2nd Season")
        foreach ($ordinalMap as $ordinal => $numericOrdinal) {
            $result = preg_replace('/\b' . preg_quote($ordinal, '/') . '\s+Season\b/i', "{$numericOrdinal} Season", $result);
        }

        return $result;
    }

    /**
     * Returns true if both titles refer to the same "volume" (or neither has a volume).
     * Used to avoid merging e.g. "Volume One" with "Volume Two: The War Years".
     */
    public static function sameVolumeOrNoVolume(?string $normalizedTitleA, ?string $normalizedTitleB): bool
    {
        if (!$normalizedTitleA || !$normalizedTitleB) {
            return true;
        }
        $volA = self::extractVolumeToken($normalizedTitleA);
        $volB = self::extractVolumeToken($normalizedTitleB);
        if ($volA === null && $volB === null) {
            return true;
        }
        if ($volA === null || $volB === null) {
            return true; // one has volume, one doesn't – allow match (e.g. "Volume One" vs "The Young Indiana Jones Chronicles")
        }
        return $volA === $volB;
    }

    /**
     * Extract a volume token from a normalized title (e.g. "volume one" -> "one", "volume 2" -> "2").
     */
    private static function extractVolumeToken(string $normalizedTitle): ?string
    {
        if (preg_match('/\bvolume\s+(\d+|[a-z]+)\b/', $normalizedTitle, $m)) {
            return $m[1];
        }
        return null;
    }
}
