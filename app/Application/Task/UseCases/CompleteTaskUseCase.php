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

        // 既に完了済みの場合はエラー
        if ($task->getAttributes()['status'] === Task::STATUS_COMPLETED) {
            throw new \Exception('既に完了済みのタスクです。');
        }

        $data = [
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now(),
        ];

        if ($dto->updated_by !== null) {
            $data['updated_by'] = $dto->updated_by;
        }

        return $this->taskRepository->update($task, $data);
    }
}
