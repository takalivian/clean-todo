<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskControllerIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 正常なレスポンスが返されることをテストする
     * - 実際のデータベースを使用してエンドツーエンドの動作を検証
     * - 成功レスポンス（200）とJSON形式の確認
     * - データが正しく返されることを確認
     */
    public function test_index_returns_successful_response()
    {
        // Arrange: 実際のデータベースにタスクを作成
        Task::factory()->create(['title' => 'Task 1']);
        Task::factory()->create(['title' => 'Task 2']);

        // Act: HTTPリクエスト実行
        $response = $this->getJson('/api/tasks');

        // Assert: レスポンスの検証
        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['title' => 'Task 1'])
            ->assertJsonFragment(['title' => 'Task 2']);
    }

    /**
     * only_deletedパラメータが正しく処理されることをテストする
     * - 実際のソフトデリートを使用して削除済みタスクのみを取得
     * - 削除済みタスクのみのレスポンスを検証
     * - アクティブなタスクは含まれないことを確認
     */
    public function test_index_with_only_deleted_parameter()
    {
        // Arrange: アクティブなタスクと削除済みタスクを作成
        Task::factory()->create(['title' => 'Active Task']);
        $deletedTask = Task::factory()->create(['title' => 'Deleted Task']);
        $deletedTask->delete(); // 実際にソフトデリートを実行

        // Act: only_deletedパラメータ付きでHTTPリクエスト実行
        $response = $this->getJson('/api/tasks?only_deleted=true');

        // Assert: 削除済みタスクのみが返されることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Deleted Task'])
            ->assertJsonMissing(['title' => 'Active Task']);
    }

    /**
     * with_deletedパラメータが正しく処理されることをテストする
     * - 実際のソフトデリートを使用して全タスク（削除済み含む）を取得
     * - アクティブと削除済みの両方のタスクが返されることを検証
     */
    public function test_index_with_with_deleted_parameter()
    {
        // Arrange: アクティブなタスクと削除済みタスクを作成
        Task::factory()->create(['title' => 'Active Task']);
        $deletedTask = Task::factory()->create(['title' => 'Deleted Task']);
        $deletedTask->delete(); // 実際にソフトデリートを実行

        // Act: with_deletedパラメータ付きでHTTPリクエスト実行
        $response = $this->getJson('/api/tasks?with_deleted=true');

        // Assert: 全タスク（アクティブと削除済み）が返されることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['title' => 'Active Task'])
            ->assertJsonFragment(['title' => 'Deleted Task']);
    }

    /**
     * タスクが存在しない場合の空データレスポンスをテストする
     * - データベースにタスクが存在しない場合の動作を検証
     * - 空の配列が正しく返されることを確認
     * - エラーではなく正常なレスポンス（200）が返されることを確認
     */
    public function test_index_returns_empty_data_when_no_tasks()
    {
        // Arrange: データベースにタスクを作成しない

        // Act: HTTPリクエスト実行
        $response = $this->getJson('/api/tasks');

        // Assert: 空の配列が返されることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => []
            ]);
    }

    /**
     * 複数のパラメータが同時に指定された場合の動作をテストする
     * - only_deletedとwith_deletedの両方がtrueの場合の処理を検証
     * - only_deletedが優先されることを確認
     * - 削除済みタスクのみが返されることを確認
     */
    public function test_index_with_multiple_parameters()
    {
        // Arrange: アクティブなタスクと削除済みタスクを作成
        Task::factory()->create(['title' => 'Active Task']);
        $deletedTask = Task::factory()->create(['title' => 'Deleted Task']);
        $deletedTask->delete(); // 実際にソフトデリートを実行

        // Act: 両方のパラメータをtrueでHTTPリクエスト実行
        $response = $this->getJson('/api/tasks?only_deleted=true&with_deleted=true');

        // Assert: only_deletedが優先され、削除済みタスクのみが返されることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Deleted Task'])
            ->assertJsonMissing(['title' => 'Active Task']);
    }

    /**
     * タスクのステータスが文字列として返されることをテストする
     * - ステータスが数値ではなく文字列として返されることを検証
     * - 各ステータスの値が正しく変換されることを確認
     */
    public function test_index_includes_task_status_as_string()
    {
        // Arrange: 異なるステータスのタスクを作成
        Task::factory()->create(['status' => Task::STATUS_PENDING]);
        Task::factory()->create(['status' => Task::STATUS_IN_PROGRESS]);
        Task::factory()->create(['status' => Task::STATUS_COMPLETED]);

        // Act: HTTPリクエスト実行
        $response = $this->getJson('/api/tasks');

        // Assert: ステータスが文字列として返されることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonCount(3, 'data');

        $data = $response->json('data');
        $statuses = collect($data)->pluck('status')->toArray();
        
        $this->assertContains('pending', $statuses);
        $this->assertContains('in_progress', $statuses);
        $this->assertContains('completed', $statuses);
    }
}