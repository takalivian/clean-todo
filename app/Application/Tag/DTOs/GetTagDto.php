<?php

namespace App\Application\Tag\DTOs;

class GetTagDto
{
    public function __construct(
        public readonly int $id,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
        );
    }
}
