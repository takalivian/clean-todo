<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskControllerDestroyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * タスクを正常に削除（論理削除）できることをテストする
     * - 成功レスポンス（200）とJSON形式の確認
     * - データベースで論理削除されることを確認
     */
    public function test_destroy_deletes_task_successfully()
    {
        // Arrange: タスクを作成
        $task = Task::factory()->create([
            'title' => 'Task to Delete',
        ]);

        // Act: HTTPリクエスト実行
        $response = $this->deleteJson("/api/tasks/{$task->id}");

        // Assert: レスポンスの検証
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'タスクが削除されました',
            ]);

        // データベースで論理削除されたことを確認
        $this->assertSoftDeleted('tasks', [
            'id' => $task->id,
        ]);
    }

    /**
     * 各ステータスのタスクが削除できることをテストする
     */
    public function test_destroy_deletes_tasks_with_each_status()
    {
        // Arrange & Act & Assert: 各ステータスのタスクを削除
        $statuses = [
            Task::STATUS_PENDING,
            Task::STATUS_IN_PROGRESS,
            Task::STATUS_COMPLETED,
        ];

        foreach ($statuses as $status) {
            $task = Task::factory()->create([
                'status' => $status,
            ]);

            $response = $this->deleteJson("/api/tasks/{$task->id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ]);

            $this->assertSoftDeleted('tasks', [
                'id' => $task->id,
            ]);
        }
    }

    /**
     * タスクが存在しない場合にエラーが返されることをテストする
     */
    public function test_destroy_returns_error_when_task_not_found()
    {
        // Act: 存在しないIDでHTTPリクエスト実行
        $response = $this->deleteJson('/api/tasks/9999');

        // Assert: エラーが返されることを確認
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * 既に削除済みのタスクを削除しようとするとエラーが返されることをテストする
     */
    public function test_destroy_returns_error_when_task_already_deleted()
    {
        // Arrange: 削除済みタスクを作成
        $task = Task::factory()->create();
        $task->delete();

        // Act: HTTPリクエスト実行
        $response = $this->deleteJson("/api/tasks/{$task->id}");

        // Assert: エラーが返されることを確認
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => '既に削除されています。',
            ]);
    }

    /**
     * 削除後もデータベースにレコードは残ることをテストする（論理削除）
     */
    public function test_destroy_soft_deletes_task_keeping_record()
    {
        // Arrange: タスクを作成
        $task = Task::factory()->create([
            'title' => 'Task to Soft Delete',
        ]);

        // Act: HTTPリクエスト実行
        $response = $this->deleteJson("/api/tasks/{$task->id}");

        // Assert: レコードは残っているがdeleted_atが設定されている
        $response->assertStatus(200);

        $deletedTask = Task::withTrashed()->find($task->id);
        $this->assertNotNull($deletedTask);
        $this->assertNotNull($deletedTask->deleted_at);
    }

    /**
     * 削除後に通常の取得では取得できなくなることをテストする
     */
    public function test_destroy_makes_task_unavailable_for_normal_queries()
    {
        // Arrange: タスクを作成
        $task = Task::factory()->create();

        // Act: タスクを削除
        $this->deleteJson("/api/tasks/{$task->id}");

        // Assert: 通常のクエリでは取得できない
        $result = Task::find($task->id);
        $this->assertNull($result);

        // withTrashed()を使えば取得できる
        $trashedTask = Task::withTrashed()->find($task->id);
        $this->assertNotNull($trashedTask);
    }

    /**
     * 複数のタスクを個別に削除できることをテストする
     */
    public function test_destroy_can_delete_multiple_tasks_individually()
    {
        // Arrange: 複数のタスクを作成
        $task1 = Task::factory()->create();
        $task2 = Task::factory()->create();
        $task3 = Task::factory()->create();

        // Act: 各タスクを削除
        $this->deleteJson("/api/tasks/{$task1->id}")->assertStatus(200);
        $this->deleteJson("/api/tasks/{$task2->id}")->assertStatus(200);
        $this->deleteJson("/api/tasks/{$task3->id}")->assertStatus(200);

        // Assert: 全てのタスクが削除されている
        $this->assertSoftDeleted('tasks', ['id' => $task1->id]);
        $this->assertSoftDeleted('tasks', ['id' => $task2->id]);
        $this->assertSoftDeleted('tasks', ['id' => $task3->id]);
    }

    /**
     * 削除されたタスクはindexで取得されないことをテストする
     */
    public function test_destroy_removed_task_not_in_index()
    {
        // Arrange: タスクを作成
        $activeTask = Task::factory()->create(['title' => 'Active Task']);
        $taskToDelete = Task::factory()->create(['title' => 'Task to Delete']);

        // Act: 1つのタスクを削除
        $this->deleteJson("/api/tasks/{$taskToDelete->id}");

        // Assert: indexには削除されたタスクが含まれない
        $response = $this->getJson('/api/tasks');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Active Task'])
            ->assertJsonMissing(['title' => 'Task to Delete']);
    }

    /**
     * 削除されたタスクはonly_deleted=trueで取得できることをテストする
     */
    public function test_destroy_deleted_task_can_be_retrieved_with_only_deleted_flag()
    {
        // Arrange: タスクを作成して削除
        $task = Task::factory()->create(['title' => 'Deleted Task']);
        $this->deleteJson("/api/tasks/{$task->id}");

        // Act: only_deleted=trueでタスクを取得
        $response = $this->getJson('/api/tasks?only_deleted=true');

        // Assert: 削除されたタスクが取得できる
        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Deleted Task']);
    }

    /**
     * 無効なID形式でエラーが返されることをテストする
     */
    public function test_destroy_returns_error_for_invalid_id_format()
    {
        // Act: 無効なIDでHTTPリクエスト実行
        $response = $this->deleteJson('/api/tasks/invalid');

        // Assert: エラーが返されることを確認
        $response->assertStatus(400);
    }

    /**
     * 負の数のIDでエラーが返されることをテストする
     */
    public function test_destroy_returns_error_for_negative_id()
    {
        // Act: 負のIDでHTTPリクエスト実行
        $response = $this->deleteJson('/api/tasks/-1');

        // Assert: エラーが返されることを確認
        $response->assertStatus(400);
    }

    /**
     * 完了済みタスクも削除できることをテストする
     * - 削除に関してステータスは関係ない
     */
    public function test_destroy_can_delete_completed_task()
    {
        // Arrange: 完了済みタスクを作成
        $task = Task::factory()->create([
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        // Act: HTTPリクエスト実行
        $response = $this->deleteJson("/api/tasks/{$task->id}");

        // Assert: 削除できることを確認
        $response->assertStatus(200);
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }
}
