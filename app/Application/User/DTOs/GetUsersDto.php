<?php

namespace App\Application\User\DTOs;

class GetUsersDto
{
    public function __construct()
    {
        // 将来的にフィルタリングやページネーションのパラメータを追加可能
    }

    public static function fromArray(array $data): self
    {
        return new self();
    }
}
