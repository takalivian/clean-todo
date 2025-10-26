<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskControllerRestoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 削除済みタスクを正常に復元できることをテストする
     * - 成功レスポンス（200）とJSON形式の確認
     * - deleted_atがnullになることを確認
     */
    public function test_restore_restores_deleted_task_successfully()
    {
        // Arrange: 削除済みタスクを作成
        $task = Task::factory()->create([
            'title' => 'Deleted Task',
        ]);
        $task->delete();

        // Act: HTTPリクエスト実行
        $response = $this->postJson("/api/tasks/{$task->id}/restore");

        // Assert: レスポンスの検証
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'タスクが復元されました',
            ]);

        // データベースで復元されたことを確認
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * 復元後のタスクが通常のクエリで取得できることをテストする
     */
    public function test_restore_makes_task_available_for_normal_queries()
    {
        // Arrange: 削除済みタスクを作成
        $task = Task::factory()->create([
            'title' => 'Task to Restore',
        ]);
        $task->delete();

        // 削除直後は通常のクエリで取得できないことを確認
        $this->assertNull(Task::find($task->id));

        // Act: タスクを復元
        $this->postJson("/api/tasks/{$task->id}/restore");

        // Assert: 復元後は通常のクエリで取得できる
        $restoredTask = Task::find($task->id);
        $this->assertNotNull($restoredTask);
        $this->assertEquals('Task to Restore', $restoredTask->title);
    }

    /**
     * 各ステータスの削除済みタスクが復元できることをテストする
     */
    public function test_restore_restores_tasks_with_each_status()
    {
        // Arrange & Act & Assert: 各ステータスのタスクを復元
        $statuses = [
            Task::STATUS_PENDING,
            Task::STATUS_IN_PROGRESS,
            Task::STATUS_COMPLETED,
        ];

        foreach ($statuses as $status) {
            $task = Task::factory()->create([
                'status' => $status,
            ]);
            $task->delete();

            $response = $this->postJson("/api/tasks/{$task->id}/restore");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ]);

            $this->assertDatabaseHas('tasks', [
                'id' => $task->id,
                'deleted_at' => null,
            ]);
        }
    }

    /**
     * アクティブなタスクを復元しようとするとエラーが返されることをテストする
     */
    public function test_restore_returns_error_for_active_task()
    {
        // Arrange: アクティブなタスクを作成
        $task = Task::factory()->create([
            'title' => 'Active Task',
        ]);

        // Act: アクティブなタスクを復元しようとする
        $response = $this->postJson("/api/tasks/{$task->id}/restore");

        // Assert: エラーが返されることを確認
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * タスクが存在しない場合にエラーが返されることをテストする
     */
    public function test_restore_returns_error_when_task_not_found()
    {
        // Act: 存在しないIDでHTTPリクエスト実行
        $response = $this->postJson('/api/tasks/9999/restore');

        // Assert: エラーが返されることを確認
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * レスポンスに復元後のタスクデータが含まれることをテストする
     */
    public function test_restore_returns_restored_task_data()
    {
        // Arrange: 削除済みタスクを作成
        $task = Task::factory()->create();
        $task->delete();

        // Act: HTTPリクエスト実行
        $response = $this->postJson("/api/tasks/{$task->id}/restore");

        // Assert: 復元後のデータが返されることを確認
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'status',
                    'due_date',
                    'completed_at',
                    'created_at',
                    'updated_at',
                ]
            ]);
    }

    /**
     * 復元後もタスクのデータは保持されることをテストする
     * - 削除前のデータが全て保持される
     */
    public function test_restore_preserves_task_data()
    {
        // Arrange: タスクを作成
        $originalTitle = 'Original Title';
        $originalDescription = 'Original Description';
        $originalStatus = Task::STATUS_IN_PROGRESS;

        $task = Task::factory()->create([
            'title' => $originalTitle,
            'description' => $originalDescription,
            'status' => $originalStatus,
        ]);
        $taskId = $task->id;
        $task->delete();

        // Act: タスクを復元
        $this->postJson("/api/tasks/{$taskId}/restore");

        // Assert: データが保持されていることを確認
        $restoredTask = Task::find($taskId);
        $this->assertEquals($originalTitle, $restoredTask->title);
        $this->assertEquals($originalDescription, $restoredTask->description);
        $this->assertEquals($originalStatus, $restoredTask->getAttributes()['status']);
    }

    /**
     * 完了済みタスクを削除して復元できることをテストする
     * - completed_atも保持される
     */
    public function test_restore_preserves_completed_at()
    {
        // Arrange: 完了済みタスクを作成して削除
        $completedAt = now()->subDay();
        $task = Task::factory()->create([
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => $completedAt,
        ]);
        $taskId = $task->id;
        $task->delete();

        // Act: タスクを復元
        $this->postJson("/api/tasks/{$taskId}/restore");

        // Assert: completed_atが保持されていることを確認
        $restoredTask = Task::find($taskId);
        $this->assertEquals(Task::STATUS_COMPLETED, $restoredTask->getAttributes()['status']);
        $this->assertNotNull($restoredTask->completed_at);
    }

    /**
     * 複数のタスクを個別に復元できることをテストする
     */
    public function test_restore_can_restore_multiple_tasks_individually()
    {
        // Arrange: 複数のタスクを作成して削除
        $task1 = Task::factory()->create(['title' => 'Task 1']);
        $task2 = Task::factory()->create(['title' => 'Task 2']);
        $task3 = Task::factory()->create(['title' => 'Task 3']);

        $task1->delete();
        $task2->delete();
        $task3->delete();

        // Act: 各タスクを復元
        $this->postJson("/api/tasks/{$task1->id}/restore")->assertStatus(200);
        $this->postJson("/api/tasks/{$task2->id}/restore")->assertStatus(200);
        $this->postJson("/api/tasks/{$task3->id}/restore")->assertStatus(200);

        // Assert: 全てのタスクが復元されている
        $this->assertNotNull(Task::find($task1->id));
        $this->assertNotNull(Task::find($task2->id));
        $this->assertNotNull(Task::find($task3->id));
    }

    /**
     * 復元後のタスクがindexで取得されることをテストする
     */
    public function test_restore_restored_task_appears_in_index()
    {
        // Arrange: タスクを作成して削除
        $task = Task::factory()->create(['title' => 'Restored Task']);
        $task->delete();

        // Act: タスクを復元
        $this->postJson("/api/tasks/{$task->id}/restore");

        // Assert: indexに復元されたタスクが含まれる
        $response = $this->getJson('/api/tasks');
        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Restored Task']);
    }

    /**
     * 同じタスクを複数回復元してもエラーにならないことをテストする
     * - 冪等性の確認（2回目以降はアクティブなので失敗する）
     */
    public function test_restore_fails_when_restoring_already_restored_task()
    {
        // Arrange: タスクを作成して削除
        $task = Task::factory()->create();
        $task->delete();

        // Act: 1回目の復元（成功）
        $response1 = $this->postJson("/api/tasks/{$task->id}/restore");
        $response1->assertStatus(200);

        // Act: 2回目の復元（失敗 - アクティブなタスク）
        $response2 = $this->postJson("/api/tasks/{$task->id}/restore");

        // Assert: 2回目はエラーが返される
        $response2->assertStatus(400);
    }

    /**
     * 無効なID形式でエラーが返されることをテストする
     */
    public function test_restore_returns_error_for_invalid_id_format()
    {
        // Act: 無効なIDでHTTPリクエスト実行
        $response = $this->postJson('/api/tasks/invalid/restore');

        // Assert: エラーが返されることを確認
        $response->assertStatus(400);
    }

    /**
     * 負の数のIDでエラーが返されることをテストする
     */
    public function test_restore_returns_error_for_negative_id()
    {
        // Act: 負のIDでHTTPリクエスト実行
        $response = $this->postJson('/api/tasks/-1/restore');

        // Assert: エラーが返されることを確認
        $response->assertStatus(400);
    }
}
