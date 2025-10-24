<?php

namespace App\Application\Task\UseCases;

use App\Application\Task\DTOs\CompleteTaskDto;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Models\Task;

class CompleteTaskUseCase
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {
    }

    public function execute(CompleteTaskDto $dto): Task
    {
        $task = $this->taskRepository->findById($dto->id);

        if (!$task) {
            throw new \Exception('タスクが見つかりません。');
        }

        if ($task->trashed()) {
            throw new \Exception('削除済みのタスクは完了できません。');
        }

        $data = [
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now(),
        ];

        return $this->taskRepository->update($task, $data);
    }
}
