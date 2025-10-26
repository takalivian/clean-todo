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
            onlyDeleted: self::toBool($data['only_deleted'] ?? false),
            withDeleted: self::toBool($data['with_deleted'] ?? false),
        );
    }

    /**
     * 値をboolに変換する（true/false以外はfalse）
     */
    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        // true/false以外の値はすべてfalse
        return false;
    }
}
