<?php

namespace App\Application\Task\UseCases;

use App\Application\Task\DTOs\GetTasksDto;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class GetTasksUseCase
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
    ) {
    }

    /**
     * タスク一覧を取得する
     *
     * @param GetTasksDto $dto
     * @return Collection
     */
    public function execute(GetTasksDto $dto): Collection
    {
        return $this->taskRepository->findAll(
            onlyDeleted: $dto->onlyDeleted,
            withDeleted: $dto->withDeleted
        );
    }
}
