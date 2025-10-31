<?php

namespace App\Application\Task\DTOs;

class GetTaskStatisticsByUserDto
{
    public function __construct(
        public readonly int $limit = 5,
    ) {}

    public static function fromArray(array $data): self
    {
        $limit = $data['limit'] ?? 5;

        // 文字列の場合は整数に変換、無効な値の場合はデフォルト値を使用
        if (is_string($limit)) {
            $limit = filter_var($limit, FILTER_VALIDATE_INT);
            if ($limit === false) {
                $limit = 5;
            }
        }

        return new self(
            limit: (int)$limit,
        );
    }

    public function toArray(): array
    {
        return [
            'limit' => $this->limit,
        ];
    }
}
