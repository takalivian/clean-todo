<?php

namespace App\Application\Tag\DTOs;

class UpdateTagDto
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $name,
        public readonly ?int $updated_by = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            name: $data['name'] ?? null,
            updated_by: $data['updated_by'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->updated_by !== null) {
            $data['updated_by'] = $this->updated_by;
        }

        return $data;
    }
}
