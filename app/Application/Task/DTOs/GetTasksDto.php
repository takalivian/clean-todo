<?php

namespace App\Application\Task\DTOs;

class GetTasksDto
{
    public function __construct(
        public readonly bool $onlyDeleted = false,
        public readonly bool $withDeleted = false,
        public readonly ?int $status = null,
        public readonly ?int $userId = null,
        public readonly ?string $keyword = null,
        public readonly ?string $dueDateFrom = null,
        public readonly ?string $dueDateTo = null,
        public readonly string $sortBy = 'created_at',
        public readonly string $sortDirection = 'desc',
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $sortDirection = $data['sort_direction'] ?? 'desc';
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        return new self(
            onlyDeleted: self::toBool($data['only_deleted'] ?? false),
            withDeleted: self::toBool($data['with_deleted'] ?? false),
            status: isset($data['status']) ? (int)$data['status'] : null,
            userId: isset($data['user_id']) ? (int)$data['user_id'] : null,
            keyword: $data['keyword'] ?? null,
            dueDateFrom: $data['due_date_from'] ?? null,
            dueDateTo: $data['due_date_to'] ?? null,
            sortBy: $data['sort_by'] ?? 'created_at',
            sortDirection: $sortDirection,
            page: max(1, (int)($data['page'] ?? 1)),
            perPage: min(100, max(1, (int)($data['per_page'] ?? 15))),
        );
    }

    /**
     * 値をboolに変換する
     */
    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        // 文字列の'true', '1', 'yes'などをtrueとして扱う
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
        }

        // 数値の1をtrueとして扱う
        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        return false;
    }
}
