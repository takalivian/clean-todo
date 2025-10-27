<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * 認証されたユーザーを作成してActing Asする
     *
     * @param array $attributes
     * @return User
     */
    protected function actingAsUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->actingAs($user, 'sanctum');
        return $user;
    }
}
