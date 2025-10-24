<?php

namespace App\Application\Task\DTOs;

class GetTasksDto
{
    public function __construct(
        public readonly bool $onlyDeleted = false,
        public readonly bool $withDeleted = false,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            onlyDeleted: $data['only_deleted'] ?? false,
            withDeleted: $data['with_deleted'] ?? false,
        );
    }
}
