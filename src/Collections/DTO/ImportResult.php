<?php
declare(strict_types=1);

namespace App\Collections\DTO;

/**
 * Data Transfer Object for import operation results.
 */
class ImportResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int $importedCount,
        public readonly array $errors = [],
        public readonly array $warnings = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'imported_count' => $this->importedCount,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
