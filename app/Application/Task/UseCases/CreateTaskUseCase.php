<?php

namespace App\Application\Task\UseCases;

use App\Application\Task\DTOs\CreateTaskDto;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Models\Task;

class CreateTaskUseCase
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
    ) {
    }

    /**
     * タスクを作成する
     *
     * @param CreateTaskDto $dto
     * @return Task
     */
    public function execute(CreateTaskDto $dto): Task
    {
        // ビジネスロジック: ステータスが完了の場合はcompleted_atを設定
        $data = $dto->toArray();

        if ($data['status'] === Task::STATUS_COMPLETED) {
            $data['completed_at'] = now();
        } else {
            $data['completed_at'] = null;
        }

        $task = $this->taskRepository->create($data);

        // タスク作成により統計が変わるため、キャッシュをクリア
        GetTaskStatisticsByUserUseCase::clearCache();

        return $task;
    }
}
