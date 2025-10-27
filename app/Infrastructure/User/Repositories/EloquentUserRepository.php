<?php

namespace App\Infrastructure\User\Repositories;

use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class EloquentUserRepository implements UserRepositoryInterface
{
    /**
     * 全ユーザーを取得
     */
    public function findAll(): Collection
    {
        return User::all();
    }

    /**
     * IDでユーザーを取得
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * メールアドレスでユーザーを取得
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * 新しいユーザーを作成
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * ユーザーを更新
     */
    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user->fresh();
    }

    /**
     * ユーザーを削除
     */
    public function delete(User $user): bool
    {
        return $user->delete();
    }
}
