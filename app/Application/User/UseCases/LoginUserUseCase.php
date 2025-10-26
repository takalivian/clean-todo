<?php

namespace App\Application\User\UseCases;

use App\Application\User\DTOs\LoginUserDto;
use App\Domain\User\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class LoginUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(LoginUserDto $dto): array
    {
        // ユーザーを検索
        $user = $this->userRepository->findByEmail($dto->email);

        // ユーザーが存在しない、またはパスワードが一致しない場合
        if (!$user || !Hash::check($dto->password, $user->password)) {
            throw new \Exception('メールアドレスまたはパスワードが正しくありません。');
        }

        // トークンを生成
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
