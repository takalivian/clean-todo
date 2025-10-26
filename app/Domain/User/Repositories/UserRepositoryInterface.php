<?php

namespace App\Domain\User\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    /**
     * 全ユーザーを取得
     */
    public function findAll(): Collection;

    /**
     * IDでユーザーを取得
     */
    public function findById(int $id): ?User;

    /**
     * メールアドレスでユーザーを取得
     */
    public function findByEmail(string $email): ?User;

    /**
     * 新しいユーザーを作成
     */
    public function create(array $data): User;

    /**
     * ユーザーを更新
     */
    public function update(User $user, array $data): User;

    /**
     * ユーザーを削除
     */
    public function delete(User $user): bool;
}
