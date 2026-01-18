<?php
declare(strict_types=1);

namespace App\Collections\DTO\Film;

/**
 * Video format details.
 */
class VideoFormatDTO
{
    public function __construct(
        public readonly int $editionId,
        public readonly bool $isColor,
        public readonly bool $isBlackAndWhite,
        public readonly bool $isColorized,
        public readonly bool $isMixedColor,
        public readonly bool $is2d,
        public readonly bool $is3dAnaglyph,
        public readonly bool $is3dBluray,
        public readonly bool $is16x9,
        public readonly ?float $aspectRatio,
        public readonly bool $isFullFrame,
        public readonly bool $isLetterbox,
        public readonly bool $isPanAndScan,
        public readonly bool $isDualLayered,
        public readonly bool $isDualSided,
        public readonly ?string $videoStandard
    ) {
    }

    public function toArray(): array
    {
        return [
            'edition_id' => $this->editionId,
            'is_color' => $this->isColor,
            'is_black_and_white' => $this->isBlackAndWhite,
            'is_colorized' => $this->isColorized,
            'is_mixed_color' => $this->isMixedColor,
            'is_2d' => $this->is2d,
            'is_3d_anaglyph' => $this->is3dAnaglyph,
            'is_3d_bluray' => $this->is3dBluray,
            'is_16x9' => $this->is16x9,
            'aspect_ratio' => $this->aspectRatio,
            'is_full_frame' => $this->isFullFrame,
            'is_letterbox' => $this->isLetterbox,
            'is_pan_and_scan' => $this->isPanAndScan,
            'is_dual_layered' => $this->isDualLayered,
            'is_dual_sided' => $this->isDualSided,
            'video_standard' => $this->videoStandard,
        ];
    }
}
