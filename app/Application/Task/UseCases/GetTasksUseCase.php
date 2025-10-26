<?php

namespace App\Application\Task\UseCases;

use App\Application\Task\DTOs\GetTasksDto;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetTasksUseCase
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
    ) {
    }

    /**
     * タスク一覧を取得する（フィルタリング・ページネーション対応）
     *
     * @param GetTasksDto $dto
     * @return LengthAwarePaginator
     */
    public function execute(GetTasksDto $dto): LengthAwarePaginator
    {
        return $this->taskRepository->findAllWithFilter($dto);
    }
}
