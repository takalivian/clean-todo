<?php

namespace App\Application\Task\UseCases;

use App\Application\Task\DTOs\DetachTagsFromTaskDto;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Models\Task;

class DetachTagsFromTaskUseCase
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
    ) {
    }

    /**
     * タスクからタグを削除する
     *
     * @param DetachTagsFromTaskDto $dto
     * @return Task
     * @throws \Exception
     */
    public function execute(DetachTagsFromTaskDto $dto): Task
    {
        $task = $this->taskRepository->findById($dto->taskId);

        if (!$task) {
            throw new \Exception('タスクが見つかりません');
        }

        if ($task->trashed()) {
            throw new \Exception('削除済みのタスクのタグは削除できません');
        }

        if (empty($dto->tagIds)) {
            throw new \Exception('タグIDが指定されていません');
        }

        $this->taskRepository->detachTags($task, $dto->tagIds);

        // タグ情報を含めて再取得
        return $this->taskRepository->findById($dto->taskId);
    }
}
