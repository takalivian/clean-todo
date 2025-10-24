<?php

namespace App\Application\Task\UseCases;

use App\Application\Task\DTOs\GetTaskDto;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Models\Task;

class GetTaskUseCase
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
    ) {
    }

    /**
     * タスクを取得する
     *
     * @param GetTaskDto $dto
     * @return Task
     */
    public function execute(GetTaskDto $dto): Task
    {
        $task = $this->taskRepository->findById($dto->id);

        if (!$task) {
            throw new \Exception('タスクが見つかりません。');
        }

        return $task;
    }
}
