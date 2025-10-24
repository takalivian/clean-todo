<?php

namespace App\Application\Task\UseCases;

use App\Application\Task\DTOs\RestoreTaskDto;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Models\Task;

class RestoreTaskUseCase
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {
    }

    public function execute(RestoreTaskDto $dto): Task
    {
        $task = $this->taskRepository->findDeletedById($dto->id);

        if (!$task) {
            throw new \Exception('削除済みタスクが見つかりません。');
        }

        $this->taskRepository->restore($task);

        return $task;
    }
}
