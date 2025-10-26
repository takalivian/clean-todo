<?php

namespace App\Application\User\UseCases;

use App\Application\User\DTOs\GetUsersDto;
use App\Domain\User\Repositories\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class GetUsersUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(GetUsersDto $dto): Collection
    {
        return $this->userRepository->findAll();
    }
}
