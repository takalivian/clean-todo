<?php

namespace Tests\Unit\Infrastructure\Task\Repositories;

use App\Infrastructure\Task\Repositories\EloquentTaskRepository;
use App\Models\Task;
use App\Models\User;
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
        $user = \App\Models\User::factory()->create();
        $data = [
            'user_id' => $user->id,
            'title' => 'New Task',
            'description' => 'Task Description',
            'status' => Task::STATUS_PENDING,
            'due_date' => '2025-12-31 23:59:59',
            'completed_at' => null,
            'updated_by' => $user->id,
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
        $user = \App\Models\User::factory()->create();
        $completedAt = now();
        $data = [
            'user_id' => $user->id,
            'title' => 'Completed Task',
            'description' => 'Already done',
            'status' => Task::STATUS_COMPLETED,
            'due_date' => null,
            'completed_at' => $completedAt,
            'updated_by' => $user->id,
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
        $user = \App\Models\User::factory()->create();
        $data = [
            'user_id' => $user->id,
            'title' => 'Minimal Task',
            'description' => null,
            'status' => Task::STATUS_PENDING,
            'due_date' => null,
            'completed_at' => null,
            'updated_by' => $user->id,
        ];

        // Act: タスクを作成
        $result = $this->repository->create($data);

        // Assert: タスクが作成されることを確認
        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals($data['title'], $result->title);
        $this->assertNull($result->description);
        $this->assertNull($result->due_date);
    }

    /**
     * IDでタスクを取得できることをテストする
     * - findByIdメソッドで正しいタスクが取得されることを確認
     */
    public function test_find_by_id_returns_task()
    {
        // Arrange: テストデータの準備
        $task = Task::factory()->create(['title' => 'Test Task']);

        // Act: IDでタスクを取得
        $result = $this->repository->findById($task->id);

        // Assert: 正しいタスクが返されることを確認
        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals($task->id, $result->id);
        $this->assertEquals($task->title, $result->title);
    }

    /**
     * 存在しないIDでタスクを取得するとnullが返されることをテストする
     */
    public function test_find_by_id_returns_null_when_not_found()
    {
        // Act: 存在しないIDでタスクを取得
        $result = $this->repository->findById(9999);

        // Assert: nullが返されることを確認
        $this->assertNull($result);
    }

    /**
     * タスクを更新できることをテストする
     * - updateメソッドで正しくデータベースが更新されることを確認
     */
    public function test_update_updates_task_successfully()
    {
        // Arrange: テストデータの準備
        $task = Task::factory()->create([
            'title' => 'Original Title',
            'status' => Task::STATUS_PENDING,
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'status' => Task::STATUS_IN_PROGRESS,
        ];

        // Act: タスクを更新
        $result = $this->repository->update($task, $updateData);

        // Assert: タスクが更新されることを確認
        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals($updateData['title'], $result->title);
        $this->assertEquals(Task::STATUS_IN_PROGRESS, $result->getAttributes()['status']);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => $updateData['title'],
        ]);
    }

    /**
     * タスクを削除（論理削除）できることをテストする
     * - deleteメソッドで正しく論理削除されることを確認
     */
    public function test_delete_soft_deletes_task()
    {
        // Arrange: テストデータの準備
        $task = Task::factory()->create(['title' => 'Task to Delete']);

        // Act: タスクを削除
        $result = $this->repository->delete($task);

        // Assert: 論理削除されることを確認
        $this->assertTrue($result);
        $this->assertSoftDeleted('tasks', [
            'id' => $task->id,
        ]);
    }

    /**
     * 削除済みタスクをIDで取得できることをテストする
     * - findDeletedByIdメソッドで削除済みタスクが取得されることを確認
     */
    public function test_find_deleted_by_id_returns_deleted_task()
    {
        // Arrange: 削除済みタスクを準備
        $task = Task::factory()->create(['title' => 'Deleted Task']);
        $task->delete();

        // Act: 削除済みタスクを取得
        $result = $this->repository->findDeletedById($task->id);

        // Assert: 削除済みタスクが返されることを確認
        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals($task->id, $result->id);
        $this->assertEquals($task->title, $result->title);
        $this->assertNotNull($result->deleted_at);
    }

    /**
     * アクティブなタスクはfindDeletedByIdで取得できないことをテストする
     */
    public function test_find_deleted_by_id_returns_null_for_active_task()
    {
        // Arrange: アクティブなタスクを準備
        $task = Task::factory()->create(['title' => 'Active Task']);

        // Act: アクティブなタスクを削除済みとして取得しようとする
        $result = $this->repository->findDeletedById($task->id);

        // Assert: nullが返されることを確認
        $this->assertNull($result);
    }

    /**
     * タスクを復元できることをテストする
     * - restoreメソッドで削除済みタスクが復元されることを確認
     */
    public function test_restore_restores_deleted_task()
    {
        // Arrange: 削除済みタスクを準備
        $task = Task::factory()->create(['title' => 'Deleted Task']);
        $task->delete();

        // Act: タスクを復元
        $result = $this->repository->restore($task);

        // Assert: タスクが復元されることを確認
        $this->assertTrue($result);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * ユーザー別のタスク作成数を取得できることをテストする
     * - getTaskCountByUserメソッドが正しく統計を返すことを確認
     */
    public function test_get_task_count_by_user_returns_statistics()
    {
        // Arrange: 3人のユーザーにそれぞれタスクを作成
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        Task::factory()->count(5)->create(['user_id' => $user1->id]);
        Task::factory()->count(10)->create(['user_id' => $user2->id]);
        Task::factory()->count(3)->create(['user_id' => $user3->id]);

        // Act: ユーザー別タスク数を取得（Top 3）
        $result = $this->repository->getTaskCountByUser(3);

        // Assert: 結果の検証
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(3, $result);

        // タスク数が降順であることを確認
        $this->assertEquals(10, $result[0]['task_count']);
        $this->assertEquals(5, $result[1]['task_count']);
        $this->assertEquals(3, $result[2]['task_count']);

        // ユーザー情報が含まれることを確認
        $this->assertEquals($user2->id, $result[0]['user']->id);
        $this->assertEquals($user1->id, $result[1]['user']->id);
        $this->assertEquals($user3->id, $result[2]['user']->id);
    }

    /**
     * limit=1でトップユーザーのみ取得できることをテストする
     */
    public function test_get_task_count_by_user_with_limit_one()
    {
        // Arrange: 3人のユーザーにタスクを作成
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        Task::factory()->count(5)->create(['user_id' => $user1->id]);
        Task::factory()->count(15)->create(['user_id' => $user2->id]); // 最多
        Task::factory()->count(3)->create(['user_id' => $user3->id]);

        // Act: ユーザー別タスク数を取得（Top 1）
        $result = $this->repository->getTaskCountByUser(1);

        // Assert: トップユーザーのみが返される
        $this->assertCount(1, $result);
        $this->assertEquals($user2->id, $result[0]['user']->id);
        $this->assertEquals(15, $result[0]['task_count']);
    }

    /**
     * タスクが存在しない場合に空のコレクションが返されることをテストする
     */
    public function test_get_task_count_by_user_returns_empty_when_no_tasks()
    {
        // Arrange: タスクを作成しない

        // Act: ユーザー別タスク数を取得
        $result = $this->repository->getTaskCountByUser(5);

        // Assert: 空のコレクションが返される
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertEmpty($result);
    }

    /**
     * 削除済みタスクも集計に含まれることをテストする
     */
    public function test_get_task_count_by_user_includes_deleted_tasks()
    {
        // Arrange: ユーザーとタスクを作成、一部を削除
        $user = User::factory()->create();

        Task::factory()->count(5)->create(['user_id' => $user->id]);
        $deletedTasks = Task::factory()->count(3)->create(['user_id' => $user->id]);
        foreach ($deletedTasks as $task) {
            $task->delete();
        }

        // Act: ユーザー別タスク数を取得
        $result = $this->repository->getTaskCountByUser(5);

        // Assert: 削除済みを含めた8件がカウントされる
        $this->assertCount(1, $result);
        $this->assertEquals(8, $result[0]['task_count']);
    }

    /**
     * 同じタスク数のユーザーが複数いる場合も正しく取得できることをテストする
     */
    public function test_get_task_count_by_user_handles_same_task_count()
    {
        // Arrange: 3人のユーザーに同じ数のタスクを作成
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        Task::factory()->count(5)->create(['user_id' => $user1->id]);
        Task::factory()->count(5)->create(['user_id' => $user2->id]);
        Task::factory()->count(5)->create(['user_id' => $user3->id]);

        // Act: ユーザー別タスク数を取得
        $result = $this->repository->getTaskCountByUser(5);

        // Assert: 3人全員が返される
        $this->assertCount(3, $result);

        // 全員タスク数が5であることを確認
        foreach ($result as $item) {
            $this->assertEquals(5, $item['task_count']);
        }
    }

    /**
     * recent_tasksが含まれることをテストする
     */
    public function test_get_task_count_by_user_includes_recent_tasks()
    {
        // Arrange: ユーザーとタスクを作成
        $user = User::factory()->create();
        $tasks = Task::factory()->count(3)->create(['user_id' => $user->id]);

        // Act: ユーザー別タスク数を取得
        $result = $this->repository->getTaskCountByUser(5);

        // Assert: recent_tasksが含まれることを確認
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('recent_tasks', $result[0]);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result[0]['recent_tasks']);
        $this->assertCount(3, $result[0]['recent_tasks']);
    }

    /**
     * limitが正しく適用されることをテストする
     */
    public function test_get_task_count_by_user_applies_limit_correctly()
    {
        // Arrange: 10人のユーザーにタスクを作成
        $users = User::factory()->count(10)->create();

        foreach ($users as $index => $user) {
            Task::factory()->count($index + 1)->create(['user_id' => $user->id]);
        }

        // Act: limit=5で取得
        $result = $this->repository->getTaskCountByUser(5);

        // Assert: 5件のみ返される
        $this->assertCount(5, $result);

        // タスク数が降順であることを確認
        for ($i = 0; $i < count($result) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $result[$i + 1]['task_count'],
                $result[$i]['task_count']
            );
        }
    }
}
