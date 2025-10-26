<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskControllerCompleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 認証されたユーザーとしてテストを実行
        $this->actingAsUser();
    }

    /**
     * タスクを正常に完了状態にできることをテストする
     * - 成功レスポンス（200）とJSON形式の確認
     * - ステータスがCOMPLETEDになることを確認
     * - completed_atが設定されることを確認
     */
    public function test_complete_completes_task_successfully()
    {
        // Arrange: 未完了タスクを作成
        $task = Task::factory()->create([
            'status' => Task::STATUS_PENDING,
            'completed_at' => null,
        ]);

        // Act: HTTPリクエスト実行
        $response = $this->postJson("/api/tasks/{$task->id}/complete");

        // Assert: レスポンスの検証
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'タスクが完了しました',
            ]);

        // データベースが更新されたことを確認
        $task->refresh();
        $this->assertEquals(Task::STATUS_COMPLETED, $task->getAttributes()['status']);
        $this->assertNotNull($task->completed_at);
    }

    /**
     * 未着手タスクを完了できることをテストする
     */
    public function test_complete_can_complete_pending_task()
    {
        // Arrange: 未着手タスクを作成
        $task = Task::factory()->create([
            'status' => Task::STATUS_PENDING,
        ]);

        // Act: HTTPリクエスト実行
        $response = $this->postJson("/api/tasks/{$task->id}/complete");

        // Assert: 完了できることを確認
        $response->assertStatus(200);

        $task->refresh();
        $this->assertEquals(Task::STATUS_COMPLETED, $task->getAttributes()['status']);
    }

    /**
     * 進行中タスクを完了できることをテストする
     */
    public function test_complete_can_complete_in_progress_task()
    {
        // Arrange: 進行中タスクを作成
        $task = Task::factory()->create([
            'status' => Task::STATUS_IN_PROGRESS,
        ]);

        // Act: HTTPリクエスト実行
        $response = $this->postJson("/api/tasks/{$task->id}/complete");

        // Assert: 完了できることを確認
        $response->assertStatus(200);

        $task->refresh();
        $this->assertEquals(Task::STATUS_COMPLETED, $task->getAttributes()['status']);
    }

    /**
     * 既に完了済みのタスクを完了しようとするとエラーが返されることをテストする
     */
    public function test_complete_returns_error_for_already_completed_task()
    {
        // Arrange: 完了済みタスクを作成
        $originalCompletedAt = now()->subDay();
        $task = Task::factory()->create([
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => $originalCompletedAt,
        ]);

        // Act: HTTPリクエスト実行
        $response = $this->postJson("/api/tasks/{$task->id}/complete");

        // Assert: エラーが返されることを確認
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => '既に完了済みのタスクです。',
            ]);
    }

    /**
     * タスクが存在しない場合にエラーが返されることをテストする
     */
    public function test_complete_returns_error_when_task_not_found()
    {
        // Act: 存在しないIDでHTTPリクエスト実行
        $response = $this->postJson('/api/tasks/9999/complete');

        // Assert: エラーが返されることを確認
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * 削除済みタスクは完了できないことをテストする
     */
    public function test_complete_cannot_complete_deleted_task()
    {
        // Arrange: 削除済みタスクを作成
        $task = Task::factory()->create([
            'status' => Task::STATUS_PENDING,
        ]);
        $task->delete();

        // Act: HTTPリクエスト実行
        $response = $this->postJson("/api/tasks/{$task->id}/complete");

        // Assert: エラーが返されることを確認
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => '削除済みのタスクは完了できません。',
            ]);
    }

    /**
     * レスポンスに完了後のタスクデータが含まれることをテストする
     */
    public function test_complete_returns_completed_task_data()
    {
        // Arrange: タスクを作成
        $task = Task::factory()->create([
            'status' => Task::STATUS_PENDING,
        ]);

        // Act: HTTPリクエスト実行
        $response = $this->postJson("/api/tasks/{$task->id}/complete");

        // Assert: 完了後のデータが返されることを確認
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
            ])
            ->assertJson([
                'data' => [
                    'status' => 'completed',
                ]
            ]);

        $data = $response->json('data');
        $this->assertNotNull($data['completed_at']);
    }

    /**
     * completed_atが現在時刻に近い値であることをテストする
     */
    public function test_complete_sets_completed_at_to_current_time()
    {
        // Arrange: タスクを作成
        $task = Task::factory()->create([
            'status' => Task::STATUS_PENDING,
        ]);

        $beforeComplete = now()->subSecond();

        // Act: HTTPリクエスト実行
        $response = $this->postJson("/api/tasks/{$task->id}/complete");

        $afterComplete = now()->addSecond();

        // Assert: completed_atが現在時刻付近であることを確認
        $response->assertStatus(200);

        $task->refresh();
        $this->assertNotNull($task->completed_at);
        $this->assertTrue($task->completed_at->greaterThanOrEqualTo($beforeComplete));
        $this->assertTrue($task->completed_at->lessThanOrEqualTo($afterComplete));
    }

    /**
     * 複数のタスクを個別に完了できることをテストする
     */
    public function test_complete_can_complete_multiple_tasks_individually()
    {
        // Arrange: 複数のタスクを作成
        $task1 = Task::factory()->create(['status' => Task::STATUS_PENDING]);
        $task2 = Task::factory()->create(['status' => Task::STATUS_IN_PROGRESS]);
        $task3 = Task::factory()->create(['status' => Task::STATUS_PENDING]);

        // Act: 各タスクを完了
        $this->postJson("/api/tasks/{$task1->id}/complete")->assertStatus(200);
        $this->postJson("/api/tasks/{$task2->id}/complete")->assertStatus(200);
        $this->postJson("/api/tasks/{$task3->id}/complete")->assertStatus(200);

        // Assert: 全てのタスクが完了している
        $task1->refresh();
        $task2->refresh();
        $task3->refresh();

        $this->assertEquals(Task::STATUS_COMPLETED, $task1->getAttributes()['status']);
        $this->assertEquals(Task::STATUS_COMPLETED, $task2->getAttributes()['status']);
        $this->assertEquals(Task::STATUS_COMPLETED, $task3->getAttributes()['status']);
    }

    /**
     * 無効なID形式でエラーが返されることをテストする
     */
    public function test_complete_returns_error_for_invalid_id_format()
    {
        // Act: 無効なIDでHTTPリクエスト実行
        $response = $this->postJson('/api/tasks/invalid/complete');

        // Assert: エラーが返されることを確認
        $response->assertStatus(400);
    }

    /**
     * 負の数のIDでエラーが返されることをテストする
     */
    public function test_complete_returns_error_for_negative_id()
    {
        // Act: 負のIDでHTTPリクエスト実行
        $response = $this->postJson('/api/tasks/-1/complete');

        // Assert: エラーが返されることを確認
        $response->assertStatus(400);
    }

    /**
     * タスクのその他のフィールドは変更されないことをテストする
     * - ステータスとcompleted_at以外は変更されない
     */
    public function test_complete_only_changes_status_and_completed_at()
    {
        // Arrange: タスクを作成
        $originalTitle = 'Original Title';
        $originalDescription = 'Original Description';
        $originalDueDate = '2025-12-31 23:59:59';

        $task = Task::factory()->create([
            'title' => $originalTitle,
            'description' => $originalDescription,
            'due_date' => $originalDueDate,
            'status' => Task::STATUS_PENDING,
        ]);

        // Act: HTTPリクエスト実行
        $this->postJson("/api/tasks/{$task->id}/complete");

        // Assert: その他のフィールドは変更されていない
        $task->refresh();
        $this->assertEquals($originalTitle, $task->title);
        $this->assertEquals($originalDescription, $task->description);
    }
}
