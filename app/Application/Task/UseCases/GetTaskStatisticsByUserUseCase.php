<?php

namespace App\Application\Task\UseCases;

use App\Application\Task\DTOs\GetTaskStatisticsByUserDto;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use Illuminate\Support\Collection;

class GetTaskStatisticsByUserUseCase
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {}

    /**
     * ユーザー別のタスク作成数統計を取得する
     *
     * @param GetTaskStatisticsByUserDto $dto
     * @return Collection
     */
    public function execute(GetTaskStatisticsByUserDto $dto): Collection
    {
        return $this->taskRepository->getTaskCountByUser($dto->limit);
    }
}
