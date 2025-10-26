<?php

namespace App\Application\Task\DTOs;

class CompleteTaskDto
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $updated_by = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            updated_by: $data['updated_by'] ?? null,
        );
    }
}
