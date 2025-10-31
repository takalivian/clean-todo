<?php

namespace App\Application\Tag\DTOs;

class CreateTagDto
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $name,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            user_id: $data['user_id'],
            name: $data['name'],
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'name' => $this->name,
        ];
    }
}
