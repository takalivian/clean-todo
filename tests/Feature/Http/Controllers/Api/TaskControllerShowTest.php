<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskControllerShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 認証されたユーザーとしてテストを実行
        $this->actingAsUser();
    }

    /**
     * 正常なレスポンスが返されることをテストする
     * - 実際のデータベースを使用してエンドツーエンドの動作を検証
     * - 成功レスポンス（200）とJSON形式の確認
     * - タスクデータが正しく返されることを確認
     */
    public function test_show_returns_successful_response()
    {
        // Arrange: 実際のデータベースにタスクを作成
        $task = Task::factory()->create([
            'title' => 'Test Task',
            'description' => 'Test Description',
            'status' => Task::STATUS_PENDING,
        ]);

        // Act: HTTPリクエスト実行
        $response = $this->getJson("/api/tasks/{$task->id}");

        // Assert: レスポンスの検証
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $task->id,
                    'title' => 'Test Task',
                    'description' => 'Test Description',
                ]
            ])
            ->assertJsonStructure([
                'success',
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
     * タスクが見つからない場合に404が返されることをテストする
     * - 存在しないIDを指定した場合の動作を確認
     * - エラーレスポンス（404）が返されることを確認
     * - エラーメッセージが日本語で返されることを確認
     */
    public function test_show_returns_404_when_task_not_found()
    {
        // Act: 存在しないIDでHTTPリクエスト実行
        $response = $this->getJson('/api/tasks/9999');

        // Assert: 404エラーが返されることを確認
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'タスクが見つかりません。',
            ]);
    }

    /**
     * ステータスが文字列として返されることをテストする
     * - アクセサの動作確認
     * - ステータスが"pending"などの文字列で返されることを確認
     */
    public function test_show_returns_status_as_string()
    {
        // Arrange: 進行中ステータスのタスクを作成
        $task = Task::factory()->create([
            'status' => Task::STATUS_IN_PROGRESS,
        ]);

        // Act: HTTPリクエスト実行
        $response = $this->getJson("/api/tasks/{$task->id}");

        // Assert: ステータスが文字列として返されることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'in_progress',
                ]
            ]);
    }

    /**
     * 各ステータスのタスクが正しく取得できることをテストする
     * - 全てのステータス値（0,1,2）が正しく処理されることを確認
     */
    public function test_show_returns_tasks_with_each_status()
    {
        // Arrange & Act & Assert: 各ステータスでタスクを取得
        $statuses = [
            Task::STATUS_PENDING => 'pending',
            Task::STATUS_IN_PROGRESS => 'in_progress',
            Task::STATUS_COMPLETED => 'completed',
        ];

        foreach ($statuses as $statusValue => $statusString) {
            $task = Task::factory()->create([
                'title' => "Task - Status {$statusValue}",
                'status' => $statusValue,
            ]);

            $response = $this->getJson("/api/tasks/{$task->id}");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'status' => $statusString,
                    ]
                ]);
        }
    }

    /**
     * 完了済みタスクのcompleted_atが返されることをテストする
     * - 完了日時が正しく返されることを確認
     */
    public function test_show_returns_completed_at_for_completed_task()
    {
        // Arrange: 完了済みタスクを作成
        $completedAt = now();
        $task = Task::factory()->create([
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => $completedAt,
        ]);

        // Act: HTTPリクエスト実行
        $response = $this->getJson("/api/tasks/{$task->id}");

        // Assert: completed_atが返されることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $this->assertNotNull($data['completed_at']);
    }

    /**
     * 未完了タスクのcompleted_atがnullであることをテストする
     * - 未完了タスクはcompleted_atがnull
     */
    public function test_show_returns_null_completed_at_for_pending_task()
    {
        // Arrange: 未完了タスクを作成
        $task = Task::factory()->create([
            'status' => Task::STATUS_PENDING,
            'completed_at' => null,
        ]);

        // Act: HTTPリクエスト実行
        $response = $this->getJson("/api/tasks/{$task->id}");

        // Assert: completed_atがnullであることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'completed_at' => null,
                ]
            ]);
    }

    /**
     * 削除済みタスクも取得できることをテストする
     * - ソフトデリートされたタスクも正常に取得可能
     */
    public function test_show_returns_deleted_task()
    {
        // Arrange: 削除済みタスクを作成
        $task = Task::factory()->create([
            'title' => 'Deleted Task',
        ]);
        $taskId = $task->id;
        $task->delete();

        // Act: HTTPリクエスト実行
        $response = $this->getJson("/api/tasks/{$taskId}");

        // Assert: 正常に取得できることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $taskId,
                    'title' => 'Deleted Task',
                ]
            ]);
    }

    /**
     * due_dateが正しく返されることをテストする
     * - 期限日が設定されている場合、正しく返されることを確認
     */
    public function test_show_returns_due_date()
    {
        // Arrange: 期限日付きタスクを作成
        $dueDate = '2025-12-31 23:59:59';
        $task = Task::factory()->create([
            'due_date' => $dueDate,
        ]);

        // Act: HTTPリクエスト実行
        $response = $this->getJson("/api/tasks/{$task->id}");

        // Assert: due_dateが返されることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        $this->assertNotNull($data['due_date']);
    }

    /**
     * due_dateがnullのタスクが取得できることをテストする
     */
    public function test_show_returns_null_due_date()
    {
        // Arrange: 期限日なしタスクを作成
        $task = Task::factory()->create([
            'due_date' => null,
        ]);

        // Act: HTTPリクエスト実行
        $response = $this->getJson("/api/tasks/{$task->id}");

        // Assert: due_dateがnullであることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'due_date' => null,
                ]
            ]);
    }

    /**
     * descriptionが長いテキストでも正しく返されることをテストする
     * - 大きなテキストフィールドの処理を確認
     */
    public function test_show_returns_task_with_long_description()
    {
        // Arrange: 長い説明を持つタスクを作成
        $longDescription = str_repeat('これは長い説明です。', 100);
        $task = Task::factory()->create([
            'description' => $longDescription,
        ]);

        // Act: HTTPリクエスト実行
        $response = $this->getJson("/api/tasks/{$task->id}");

        // Assert: 長い説明が正しく返されることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'description' => $longDescription,
                ]
            ]);
    }

    /**
     * レスポンスにタイムスタンプが含まれることをテストする
     * - created_atとupdated_atが返されることを確認
     */
    public function test_show_returns_timestamps()
    {
        // Arrange: タスクを作成
        $task = Task::factory()->create();

        // Act: HTTPリクエスト実行
        $response = $this->getJson("/api/tasks/{$task->id}");

        // Assert: タイムスタンプが返されることを確認
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotNull($data['created_at']);
        $this->assertNotNull($data['updated_at']);
    }

    /**
     * 数値IDと文字列IDの両方で動作することをテストする
     * - ルートパラメータが文字列として渡される場合の動作を確認
     */
    public function test_show_works_with_string_id()
    {
        // Arrange: タスクを作成
        $task = Task::factory()->create();

        // Act: 文字列IDでHTTPリクエスト実行
        $response = $this->getJson("/api/tasks/" . (string)$task->id);

        // Assert: 正常に動作することを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $task->id,
                ]
            ]);
    }

    /**
     * 無効なID形式でエラーが返されることをテストする
     * - 数値以外のIDを指定した場合の動作を確認
     */
    public function test_show_returns_404_for_invalid_id_format()
    {
        // Act: 無効なIDでHTTPリクエスト実行
        $response = $this->getJson('/api/tasks/invalid');

        // Assert: 404エラーが返されることを確認
        $response->assertStatus(404);
    }

    /**
     * 負の数のIDで404が返されることをテストする
     */
    public function test_show_returns_404_for_negative_id()
    {
        // Act: 負のIDでHTTPリクエスト実行
        $response = $this->getJson('/api/tasks/-1');

        // Assert: 404エラーが返されることを確認
        $response->assertStatus(404);
    }
}
