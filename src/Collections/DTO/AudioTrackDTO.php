<?php
declare(strict_types=1);

namespace App\Collections\DTO;

/**
 * Audio track details.
 */
class AudioTrackDTO
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $language,
        public readonly ?string $channelLayout,
        public readonly ?string $format,
        public readonly bool $isDescriptive
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'language' => $this->language,
            'channel_layout' => $this->channelLayout,
            'format' => $this->format,
            'is_descriptive' => $this->isDescriptive,
        ];
    }
}
