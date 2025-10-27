<?php

namespace App\Infrastructure\Task\Repositories;

use App\Application\Task\DTOs\GetTasksDto;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
     * フィルタ条件付きでタスク一覧を取得する（ページネーション対応）
     *
     * @param GetTasksDto $dto
     * @return LengthAwarePaginator
     */
    public function findAllWithFilter(GetTasksDto $dto): LengthAwarePaginator
    {
        $query = Task::query();

        // 削除状態のフィルタ
        if ($dto->onlyDeleted) {
            $query->onlyTrashed();
        } elseif ($dto->withDeleted) {
            $query->withTrashed();
        }

        // ステータスフィルタ
        if ($dto->status !== null) {
            $query->where('status', $dto->status);
        }

        // ユーザーIDフィルタ
        if ($dto->userId !== null) {
            $query->where('user_id', $dto->userId);
        }

        // キーワード検索（タイトルまたは説明）
        if ($dto->keyword !== null && $dto->keyword !== '') {
            $query->where(function ($q) use ($dto) {
                $q->where('title', 'like', '%' . $dto->keyword . '%')
                  ->orWhere('description', 'like', '%' . $dto->keyword . '%');
            });
        }

        // 期限日の範囲フィルタ
        if ($dto->dueDateFrom !== null) {
            $query->where('due_date', '>=', $dto->dueDateFrom);
        }
        if ($dto->dueDateTo !== null) {
            $query->where('due_date', '<=', $dto->dueDateTo);
        }

        // 並び順
        $allowedSortColumns = ['id', 'title', 'status', 'due_date', 'created_at', 'updated_at'];
        $sortBy = in_array($dto->sortBy, $allowedSortColumns) ? $dto->sortBy : 'created_at';
        $query->orderBy($sortBy, $dto->sortDirection);

        // ページネーション
        return $query->paginate($dto->perPage, ['*'], 'page', $dto->page);
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
     * 削除済みタスクも含めて取得する
     *
     * @param int $id
     * @return Task|null
     */
    public function findById(int $id): ?Task
    {
        return Task::withTrashed()->find($id);
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
