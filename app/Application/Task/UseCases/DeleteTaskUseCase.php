<?php

namespace App\Application\Task\UseCases;

use App\Application\Task\DTOs\DeleteTaskDto;
use App\Domain\Task\Repositories\TaskRepositoryInterface;

class DeleteTaskUseCase
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {
    }

    public function execute(DeleteTaskDto $dto): bool
    {
        $task = $this->taskRepository->findById($dto->id);

        if (!$task) {
            throw new \Exception('タスクが見つかりません。');
        }

        if ($task->trashed()) {
            throw new \Exception('既に削除されています。');
        }

        return $this->taskRepository->delete($task);
    }
}
