<?php

namespace App\Application\Task\UseCases;

use App\Application\Task\DTOs\AttachTagsToTaskDto;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Models\Task;

class AttachTagsToTaskUseCase
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
    ) {
    }

    /**
     * タスクにタグを付与する
     *
     * @param AttachTagsToTaskDto $dto
     * @return Task
     * @throws \Exception
     */
    public function execute(AttachTagsToTaskDto $dto): Task
    {
        $task = $this->taskRepository->findById($dto->taskId);

        if (!$task) {
            throw new \Exception('タスクが見つかりません');
        }

        if ($task->trashed()) {
            throw new \Exception('削除済みのタスクにはタグを付けられません');
        }

        if (empty($dto->tagIds)) {
            throw new \Exception('タグIDが指定されていません');
        }

        $this->taskRepository->attachTags($task, $dto->tagIds);

        // タグ情報を含めて再取得
        return $this->taskRepository->findById($dto->taskId);
    }
}
