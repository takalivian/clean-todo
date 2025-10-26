<?php

namespace App\Domain\Task\Repositories;

use App\Application\Task\DTOs\GetTasksDto;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TaskRepositoryInterface
{
    /**
     * タスク一覧を取得する
     *
     * @param bool $onlyDeleted 削除済みのみ取得（優先度: 高）
     * @param bool $withDeleted 削除済みを含めて取得（優先度: 低）
     * @return \Illuminate\Database\Eloquent\Collection
     *
     * 注意: onlyDeleted=true, withDeleted=true の場合、onlyDeletedが優先される
     */
    public function findAll(bool $onlyDeleted = false, bool $withDeleted = false);

    /**
     * フィルタ条件付きでタスク一覧を取得する（ページネーション対応）
     *
     * @param GetTasksDto $dto
     * @return LengthAwarePaginator
     */
    public function findAllWithFilter(GetTasksDto $dto): LengthAwarePaginator;

    /**
     * タスクを作成する
     *
     * @param array $data
     * @return Task
     */
    public function create(array $data): Task;

    /**
     * IDでタスクを取得する
     *
     * @param int $id
     * @return Task|null
     */
    public function findById(int $id): ?Task;

    /**
     * タスクを更新する
     *
     * @param Task $task
     * @param array $data
     * @return Task
     */
    public function update(Task $task, array $data): Task;

    /**
     * タスクを削除する（論理削除）
     *
     * @param Task $task
     * @return bool
     */
    public function delete(Task $task): bool;

    /**
     * 削除されたタスクをIDで取得する
     *
     * @param int $id
     * @return Task|null
     */
    public function findDeletedById(int $id): ?Task;

    /**
     * タスクを復元する
     *
     * @param Task $task
     * @return bool
     */
    public function restore(Task $task): bool;
}
