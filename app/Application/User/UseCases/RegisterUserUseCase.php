<?php

namespace App\Application\User\UseCases;

use App\Application\User\DTOs\RegisterUserDto;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(RegisterUserDto $dto): User
    {
        // メールアドレスの重複チェック
        $existingUser = $this->userRepository->findByEmail($dto->email);
        if ($existingUser) {
            throw new \Exception('このメールアドレスは既に登録されています');
        }

        // パスワードをハッシュ化してユーザーを作成
        $userData = [
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => Hash::make($dto->password),
        ];

        return $this->userRepository->create($userData);
    }
}
