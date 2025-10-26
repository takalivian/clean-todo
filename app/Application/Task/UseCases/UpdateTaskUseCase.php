<?php

namespace App\Application\Task\UseCases;

use App\Application\Task\DTOs\UpdateTaskDto;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Models\Task;

class UpdateTaskUseCase
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {
    }

    public function execute(UpdateTaskDto $dto): Task
    {
        $task = $this->taskRepository->findById($dto->id);

        if (!$task) {
            throw new \Exception('タスクが見つかりません。');
        }

        // 完了済みまたは削除済みのタスクは編集不可
        // アクセサを回避して生の値を取得
        if ($task->getAttributes()['status'] === Task::STATUS_COMPLETED) {
            throw new \Exception('完了済みのタスクは編集できません。');
        }

        if ($task->trashed()) {
            throw new \Exception('削除済みのタスクは編集できません。');
        }

        $data = $dto->toArray();

        // ステータスが完了に変更された場合はcompleted_atを設定
        if (isset($data['status'])) {
            if ($data['status'] === Task::STATUS_COMPLETED) {
                $data['completed_at'] = now();
            } else {
                $data['completed_at'] = null;
            }
        }

        return $this->taskRepository->update($task, $data);
    }
}
