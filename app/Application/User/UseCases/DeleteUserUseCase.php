<?php

namespace App\Application\User\UseCases;

use App\Application\User\DTOs\DeleteUserDto;
use App\Domain\User\Repositories\UserRepositoryInterface;

class DeleteUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(DeleteUserDto $dto): bool
    {
        // ユーザーを取得
        $user = $this->userRepository->findById($dto->id);

        if (!$user) {
            throw new \Exception('ユーザーが見つかりません');
        }

        // ユーザーを削除（ソフトデリート）
        return $this->userRepository->delete($user);
    }
}
