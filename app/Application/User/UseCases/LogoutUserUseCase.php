<?php

namespace App\Application\User\UseCases;

use App\Models\User;

class LogoutUserUseCase
{
    public function execute(User $user): void
    {
        // 現在のトークンを削除
        $user->currentAccessToken()->delete();
    }
}
