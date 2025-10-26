<?php

namespace App\Application\User\UseCases;

use App\Application\User\DTOs\UpdateUserDto;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdateUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(UpdateUserDto $dto): User
    {
        // ユーザーを取得
        $user = $this->userRepository->findById($dto->id);

        if (!$user) {
            throw new \Exception('ユーザーが見つかりません');
        }

        // 更新データを準備
        $updateData = $dto->toArray();

        // パスワードが含まれている場合はハッシュ化
        if (isset($updateData['password'])) {
            $updateData['password'] = Hash::make($updateData['password']);
        }

        // メールアドレスが変更される場合は重複チェック
        if (isset($updateData['email']) && $updateData['email'] !== $user->email) {
            $existingUser = $this->userRepository->findByEmail($updateData['email']);
            if ($existingUser) {
                throw new \Exception('このメールアドレスは既に使用されています');
            }
        }

        // ユーザーを更新
        return $this->userRepository->update($user, $updateData);
    }
}
