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

        // 削除済みまたは完了済みのタスクは編集不可
        // チェックの順序: 削除済み -> 完了済み
        if ($task->trashed()) {
            throw new \Exception('削除済みのタスクは編集できません。');
        }

        // アクセサを回避して生の値を取得
        if ($task->getAttributes()['status'] === Task::STATUS_COMPLETED) {
            throw new \Exception('完了済みのタスクは編集できません。');
        }

        $data = $dto->toArray();

        // ステータスが変更される場合のcompleted_at処理
        if (isset($data['status'])) {
            $currentStatus = $task->getAttributes()['status'];

            // 完了状態に変更される場合（未完了→完了）
            if ($data['status'] === Task::STATUS_COMPLETED && $currentStatus !== Task::STATUS_COMPLETED) {
                $data['completed_at'] = now();
            }
            // 未完了状態に変更される場合（完了→未完了）
            elseif ($data['status'] !== Task::STATUS_COMPLETED && $currentStatus === Task::STATUS_COMPLETED) {
                $data['completed_at'] = null;
            }
            // 完了→完了、または未完了→未完了の場合は、completed_atを変更しない
            // （dataにcompleted_atを含めない）
        }

        return $this->taskRepository->update($task, $data);
    }
}
