<?php

namespace App\Application\User\DTOs;

class DeleteUserDto
{
    public function __construct(
        public readonly int $id,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
        );
    }
}
