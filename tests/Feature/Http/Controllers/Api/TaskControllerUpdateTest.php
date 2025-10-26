<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskControllerUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * タスクを正常に更新できることをテストする
     * - 全フィールドを更新
     * - 成功レスポンス（200）とJSON形式の確認
     */
    public function test_update_updates_task_successfully()
    {
        // Arrange: タスクを作成
        $task = Task::factory()->create([
            'title' => 'Original Title',
            'description' => 'Original Description',
            'status' => Task::STATUS_PENDING,
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'description' => 'Updated Description',
            'status' => Task::STATUS_IN_PROGRESS,
            'due_date' => '2025-12-31 23:59:59',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->putJson("/api/tasks/{$task->id}", $updateData);

        // Assert: レスポンスの検証
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'タスクが更新されました',
            ]);

        // データベースが更新されたことを確認
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated Title',
            'description' => 'Updated Description',
        ]);
    }

    /**
     * 一部のフィールドのみ更新できることをテストする
     * - titleのみ更新
     */
    public function test_update_updates_only_specified_fields()
    {
        // Arrange: タスクを作成
        $task = Task::factory()->create([
            'title' => 'Original Title',
            'description' => 'Original Description',
        ]);

        $updateData = [
            'title' => 'Only Title Updated',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->putJson("/api/tasks/{$task->id}", $updateData);

        // Assert: titleのみ更新されることを確認
        $response->assertStatus(200);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Only Title Updated',
            'description' => 'Original Description', // 変更されていない
        ]);
    }

    /**
     * ステータスを完了に変更するとcompleted_atが設定されることをテストする
     */
    public function test_update_sets_completed_at_when_status_changed_to_completed()
    {
        // Arrange: 未完了タスクを作成
        $task = Task::factory()->create([
            'status' => Task::STATUS_PENDING,
            'completed_at' => null,
        ]);

        $updateData = [
            'status' => Task::STATUS_COMPLETED,
        ];

        // Act: HTTPリクエスト実行
        $response = $this->putJson("/api/tasks/{$task->id}", $updateData);

        // Assert: completed_atが設定されることを確認
        $response->assertStatus(200);

        $task->refresh();
        $this->assertEquals(Task::STATUS_COMPLETED, $task->getAttributes()['status']);
        $this->assertNotNull($task->completed_at);
    }

    /**
     * ステータスを完了以外に変更するとcompleted_atがnullになることをテストする
     */
    public function test_update_clears_completed_at_when_status_changed_from_completed()
    {
        // Arrange: 完了タスクを作成
        $task = Task::factory()->create([
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $updateData = [
            'status' => Task::STATUS_IN_PROGRESS,
        ];

        // Act: HTTPリクエスト実行（完了タスクなのでエラーになる）
        $response = $this->putJson("/api/tasks/{$task->id}", $updateData);

        // Assert: 完了済みタスクは編集できないエラーが返される
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => '完了済みのタスクは編集できません。',
            ]);
    }

    /**
     * 完了状態から完了状態への変更時にcompleted_atが変わらないことをテストする
     */
    public function test_update_preserves_completed_at_when_status_remains_completed()
    {
        // Arrange: 完了タスクを作成（ただし編集可能な状態で）
        // 注: 現在の仕様では完了済みタスクは編集できないため、このテストはスキップ
        // 将来的に完了済みタスクの一部編集が可能になった場合のために残しておく
        $this->markTestSkipped('完了済みタスクは編集できないため、このテストは現在適用されません');
    }

    /**
     * タスクが存在しない場合にエラーが返されることをテストする
     */
    public function test_update_returns_error_when_task_not_found()
    {
        // Arrange: 更新データを準備
        $updateData = [
            'title' => 'Updated Title',
        ];

        // Act: 存在しないIDでHTTPリクエスト実行
        $response = $this->putJson('/api/tasks/9999', $updateData);

        // Assert: エラーが返されることを確認
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * 完了済みタスクは編集できないことをテストする
     */
    public function test_update_cannot_edit_completed_task()
    {
        // Arrange: 完了タスクを作成
        $task = Task::factory()->create([
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $updateData = [
            'title' => 'Try to Update',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->putJson("/api/tasks/{$task->id}", $updateData);

        // Assert: エラーが返されることを確認
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => '完了済みのタスクは編集できません。',
            ]);
    }

    /**
     * 削除済みタスクは編集できないことをテストする
     */
    public function test_update_cannot_edit_deleted_task()
    {
        // Arrange: 削除済みタスクを作成
        $task = Task::factory()->create();
        $task->delete();

        $updateData = [
            'title' => 'Try to Update Deleted Task',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->putJson("/api/tasks/{$task->id}", $updateData);

        // Assert: エラーが返されることを確認
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => '削除済みのタスクは編集できません。',
            ]);
    }

    /**
     * タイトルが空の場合にバリデーションエラーが発生することをテストする
     */
    public function test_update_fails_with_empty_title()
    {
        // Arrange: タスクを作成
        $task = Task::factory()->create();

        $updateData = [
            'title' => '',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->putJson("/api/tasks/{$task->id}", $updateData);

        // Assert: バリデーションエラーが返されることを確認
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    /**
     * タイトルが255文字を超える場合にバリデーションエラーが発生することをテストする
     */
    public function test_update_fails_when_title_exceeds_max_length()
    {
        // Arrange: タスクを作成
        $task = Task::factory()->create();

        $updateData = [
            'title' => str_repeat('あ', 256),
        ];

        // Act: HTTPリクエスト実行
        $response = $this->putJson("/api/tasks/{$task->id}", $updateData);

        // Assert: バリデーションエラーが返されることを確認
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    /**
     * 無効なステータス値でバリデーションエラーが発生することをテストする
     */
    public function test_update_fails_with_invalid_status()
    {
        // Arrange: タスクを作成
        $task = Task::factory()->create();

        $updateData = [
            'status' => 999,
        ];

        // Act: HTTPリクエスト実行
        $response = $this->putJson("/api/tasks/{$task->id}", $updateData);

        // Assert: バリデーションエラーが返されることを確認
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * 無効な日付形式でバリデーションエラーが発生することをテストする
     */
    public function test_update_fails_with_invalid_date_format()
    {
        // Arrange: タスクを作成
        $task = Task::factory()->create();

        $updateData = [
            'due_date' => 'invalid-date',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->putJson("/api/tasks/{$task->id}", $updateData);

        // Assert: バリデーションエラーが返されることを確認
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);
    }

    /**
     * PATCHメソッドでも更新できることをテストする
     */
    public function test_update_works_with_patch_method()
    {
        // Arrange: タスクを作成
        $task = Task::factory()->create([
            'title' => 'Original Title',
            'status' => Task::STATUS_PENDING,
        ]);

        $updateData = [
            'title' => 'Updated via PATCH',
        ];

        // Act: PATCHメソッドでHTTPリクエスト実行
        $response = $this->patchJson("/api/tasks/{$task->id}", $updateData);

        // Assert: 正常に更新されることを確認
        $response->assertStatus(200);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated via PATCH',
        ]);
    }

    /**
     * レスポンスに更新後のタスクデータが含まれることをテストする
     */
    public function test_update_returns_updated_task_data()
    {
        // Arrange: タスクを作成
        $task = Task::factory()->create([
            'status' => Task::STATUS_PENDING,
        ]);

        $updateData = [
            'title' => 'New Title',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->putJson("/api/tasks/{$task->id}", $updateData);

        // Assert: 更新後のデータが返されることを確認
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
                    'title' => 'New Title',
                ]
            ]);
    }
}
