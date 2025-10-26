<?php

namespace App\Application\User\UseCases;

use App\Models\User;

class GetCurrentUserUseCase
{
    public function execute(User $user): User
    {
        return $user;
    }
}
