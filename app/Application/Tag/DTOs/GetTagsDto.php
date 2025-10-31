<?php

namespace App\Application\Tag\DTOs;

class GetTagsDto
{
    public function __construct(
        public readonly ?int $userId = null,
        public readonly ?string $keyword = null,
        public readonly string $sortBy = 'created_at',
        public readonly string $sortDirection = 'desc',
    ) {
    }

    public static function fromArray(array $data): self
    {
        $sortDirection = $data['sort_direction'] ?? 'desc';
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        return new self(
            userId: isset($data['user_id']) ? (int)$data['user_id'] : null,
            keyword: $data['keyword'] ?? null,
            sortBy: $data['sort_by'] ?? 'created_at',
            sortDirection: $sortDirection,
        );
    }
}
