<?php

namespace App\Infrastructure\Task\Repositories;

use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Models\Task;

class EloquentTaskRepository implements TaskRepositoryInterface
{
    /**
     * タスク一覧を取得する
     *
     * @param bool $onlyDeleted 削除済みのみ取得
     * @param bool $withDeleted 削除済みを含めて取得
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findAll(bool $onlyDeleted = false, bool $withDeleted = false)
    {
        $query = Task::query();

        // パラメータの優先順位: onlyDeleted > withDeleted
        if ($onlyDeleted) {
            // 削除済みのみ取得（withDeletedの値は無視）
            $query->onlyTrashed();
        } elseif ($withDeleted) {
            // 削除済みを含む全取得
            $query->withTrashed();
        }
        // デフォルト: 削除されていないタスクのみ

        return $query->get();
    }

    /**
     * タスクを作成する
     *
     * @param array $data
     * @return Task
     */
    public function create(array $data): Task
    {
        return Task::create($data);
    }

    /**
     * IDでタスクを取得する
     *
     * @param int $id
     * @return Task|null
     */
    public function findById(int $id): ?Task
    {
        return Task::find($id);
    }

    /**
     * タスクを更新する
     *
     * @param Task $task
     * @param array $data
     * @return Task
     */
    public function update(Task $task, array $data): Task
    {
        $task->update($data);
        return $task;
    }

    /**
     * タスクを削除する（論理削除）
     *
     * @param Task $task
     * @return bool
     */
    public function delete(Task $task): bool
    {
        return $task->delete();
    }

    /**
     * 削除されたタスクをIDで取得する
     *
     * @param int $id
     * @return Task|null
     */
    public function findDeletedById(int $id): ?Task
    {
        return Task::onlyTrashed()->find($id);
    }

    /**
     * タスクを復元する
     *
     * @param Task $task
     * @return bool
     */
    public function restore(Task $task): bool
    {
        return $task->restore();
    }
}
