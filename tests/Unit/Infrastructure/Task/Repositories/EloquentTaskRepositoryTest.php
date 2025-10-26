<?php

namespace Tests\Unit\Infrastructure\Task\Repositories;

use App\Infrastructure\Task\Repositories\EloquentTaskRepository;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentTaskRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentTaskRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentTaskRepository();
    }

    /**
     * アクティブなタスクのみを取得することをテストする
     * - onlyDeleted: false, withDeleted: false でアクティブなタスクのみ取得
     * - 削除済みタスクは除外されることを確認
     */
    public function test_find_all_returns_active_tasks()
    {
        // Arrange: テストデータの準備（アクティブ2件、削除済み1件）
        Task::factory()->create(['title' => 'Active Task 1']);
        Task::factory()->create(['title' => 'Active Task 2']);
        Task::factory()->create(['title' => 'Deleted Task'])->delete();

        // Act: アクティブなタスクのみ取得
        $result = $this->repository->findAll(onlyDeleted: false, withDeleted: false);

        // Assert: アクティブなタスクのみが返されることを確認
        $this->assertCount(2, $result);
        $this->assertContains('Active Task 1', $result->pluck('title')->toArray());
        $this->assertContains('Active Task 2', $result->pluck('title')->toArray());
    }

    /**
     * 削除済みタスクのみを取得することをテストする
     * - onlyDeleted: true, withDeleted: false で削除済みタスクのみ取得
     * - アクティブなタスクは除外されることを確認
     */
    public function test_find_all_returns_only_deleted_tasks()
    {
        // Arrange: テストデータの準備（アクティブ1件、削除済み1件）
        Task::factory()->create(['title' => 'Active Task']);
        $deletedTask = Task::factory()->create(['title' => 'Deleted Task']);
        $deletedTask->delete();

        // Act: 削除済みタスクのみ取得
        $result = $this->repository->findAll(onlyDeleted: true, withDeleted: false);

        // Assert: 削除済みタスクのみが返されることを確認
        $this->assertCount(1, $result);
        $this->assertEquals('Deleted Task', $result->first()->title);
    }

    /**
     * 全タスク（削除済み含む）を取得することをテストする
     * - onlyDeleted: false, withDeleted: true で全タスク取得
     * - アクティブと削除済みの両方が含まれることを確認
     */
    public function test_find_all_returns_all_tasks_including_deleted()
    {
        // Arrange: テストデータの準備（アクティブ1件、削除済み1件）
        Task::factory()->create(['title' => 'Active Task']);
        $deletedTask = Task::factory()->create(['title' => 'Deleted Task']);
        $deletedTask->delete();

        // Act: 全タスク（削除済み含む）取得
        $result = $this->repository->findAll(onlyDeleted: false, withDeleted: true);

        // Assert: アクティブと削除済みの両方が返されることを確認
        $this->assertCount(2, $result);
    }

    /**
     * タスクが存在しない場合の空コレクション返却をテストする
     * - データベースにタスクが存在しない場合の動作を確認
     * - 空のコレクションが返され、型が正しいことを確認
     */
    public function test_find_all_returns_empty_collection_when_no_tasks()
    {
        // Act: タスクが存在しない状態で取得
        $result = $this->repository->findAll(onlyDeleted: false, withDeleted: false);

        // Assert: 空のコレクションが返されることを確認
        $this->assertEmpty($result);
        $this->assertInstanceOf(Collection::class, $result);
    }

    /**
     * パラメータが競合する場合の動作をテストする
     * - onlyDeleted: true, withDeleted: true の場合
     * - onlyDeletedが優先され、削除済みのみ取得されることを確認
     * - クエリの優先順位が正しく動作することを検証
     */
    public function test_find_all_prioritizes_only_deleted_when_both_flags_true()
    {
        // Arrange: テストデータの準備（アクティブ1件、削除済み1件）
        Task::factory()->create(['title' => 'Active Task']);
        $deletedTask = Task::factory()->create(['title' => 'Deleted Task']);
        $deletedTask->delete();

        // Act: 両方のフラグがtrueの場合（競合状態）
        $result = $this->repository->findAll(onlyDeleted: true, withDeleted: true);

        // Assert: onlyDeletedが優先され、削除済みのみ取得されることを確認
        $this->assertCount(1, $result);
        $this->assertEquals('Deleted Task', $result->first()->title);
    }

    /**
     * タスクを作成できることをテストする
     * - createメソッドで正しくデータベースに保存されることを確認
     * - 保存されたタスクが返されることを確認
     */
    public function test_create_creates_task_successfully()
    {
        // Arrange: 作成するタスクデータを準備
        $data = [
            'title' => 'New Task',
            'description' => 'Task Description',
            'status' => Task::STATUS_PENDING,
            'due_date' => '2025-12-31 23:59:59',
            'completed_at' => null,
        ];

        // Act: タスクを作成
        $result = $this->repository->create($data);

        // Assert: タスクが作成され、データベースに保存されることを確認
        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals($data['title'], $result->title);
        $this->assertEquals($data['description'], $result->description);
        $this->assertEquals(Task::STATUS_PENDING, $result->getAttributes()['status']);
        $this->assertDatabaseHas('tasks', [
            'title' => $data['title'],
            'description' => $data['description'],
        ]);
    }

    /**
     * 完了ステータスでタスクを作成できることをテストする
     * - completed_atが設定されたタスクを作成
     */
    public function test_create_creates_completed_task()
    {
        // Arrange: 完了ステータスのタスクデータを準備
        $completedAt = now();
        $data = [
            'title' => 'Completed Task',
            'description' => 'Already done',
            'status' => Task::STATUS_COMPLETED,
            'due_date' => null,
            'completed_at' => $completedAt,
        ];

        // Act: タスクを作成
        $result = $this->repository->create($data);

        // Assert: 完了ステータスとcompleted_atが設定されることを確認
        $this->assertEquals(Task::STATUS_COMPLETED, $result->getAttributes()['status']);
        $this->assertNotNull($result->completed_at);
        $this->assertDatabaseHas('tasks', [
            'title' => $data['title'],
        ]);
    }

    /**
     * 最小限のデータでタスクを作成できることをテストする
     * - titleのみでタスクが作成されることを確認
     */
    public function test_create_creates_task_with_minimal_data()
    {
        // Arrange: 最小限のデータを準備
        $data = [
            'title' => 'Minimal Task',
            'description' => null,
            'status' => Task::STATUS_PENDING,
            'due_date' => null,
            'completed_at' => null,
        ];

        // Act: タスクを作成
        $result = $this->repository->create($data);

        // Assert: タスクが作成されることを確認
        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals($data['title'], $result->title);
        $this->assertNull($result->description);
        $this->assertNull($result->due_date);
    }

}
