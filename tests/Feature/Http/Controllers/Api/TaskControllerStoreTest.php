<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskControllerStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 認証されたユーザーとしてテストを実行
        $this->actingAsUser();
    }

    /**
     * 正常なタスク作成が成功することをテストする
     * - 全フィールドを指定してタスクを作成
     * - 成功レスポンス（201）とJSON形式の確認
     * - データベースに正しく保存されることを確認
     */
    public function test_store_creates_task_with_all_fields()
    {
        // Arrange: タスクデータを準備
        $taskData = [
            'title' => 'テストタスク',
            'description' => 'これはテスト用の説明です',
            'status' => Task::STATUS_PENDING,
            'due_date' => '2025-12-31 23:59:59',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tasks', $taskData);

        // Assert: レスポンスの検証
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'タスクが作成されました',
            ])
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

        // データベースに保存されたことを確認
        $this->assertDatabaseHas('tasks', [
            'title' => 'テストタスク',
            'description' => 'これはテスト用の説明です',
        ]);
    }

    /**
     * 必須フィールド（title）のみでタスクを作成できることをテストする
     * - titleのみを指定してタスクを作成
     * - オプショナルフィールドはnullまたはデフォルト値になることを確認
     */
    public function test_store_creates_task_with_only_required_fields()
    {
        // Arrange: 必須フィールドのみのタスクデータを準備
        $taskData = [
            'title' => '最小限のタスク',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tasks', $taskData);

        // Assert: レスポンスの検証
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'タスクが作成されました',
            ]);

        // データベースに保存されたことを確認
        $this->assertDatabaseHas('tasks', [
            'title' => '最小限のタスク',
            'description' => null,
        ]);
    }

    /**
     * titleが欠けている場合にバリデーションエラーが発生することをテストする
     * - 必須フィールドが欠けている場合の動作を検証
     * - 422エラーが返されることを確認
     * - エラーメッセージが日本語で返されることを確認
     */
    public function test_store_fails_without_required_title()
    {
        // Arrange: titleを欠いたタスクデータを準備
        $taskData = [
            'description' => '説明のみ',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tasks', $taskData);

        // Assert: バリデーションエラーの確認
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title'])
            ->assertJsonFragment([
                'title' => ['タイトルは必須です']
            ]);

        // データベースに保存されていないことを確認
        $this->assertDatabaseCount('tasks', 0);
    }

    /**
     * titleが255文字を超える場合にバリデーションエラーが発生することをテストする
     * - 最大文字数制限の検証
     * - 境界値テスト
     */
    public function test_store_fails_when_title_exceeds_max_length()
    {
        // Arrange: 256文字のtitleを準備
        $taskData = [
            'title' => str_repeat('あ', 256),
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tasks', $taskData);

        // Assert: バリデーションエラーの確認
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title'])
            ->assertJsonFragment([
                'title' => ['タイトルは255文字以内で入力してください']
            ]);

        // データベースに保存されていないことを確認
        $this->assertDatabaseCount('tasks', 0);
    }

    /**
     * 255文字ちょうどのtitleは許可されることをテストする
     * - 境界値テスト（正常系）
     */
    public function test_store_succeeds_with_max_length_title()
    {
        // Arrange: 255文字のtitleを準備
        $taskData = [
            'title' => str_repeat('あ', 255),
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tasks', $taskData);

        // Assert: 正常に作成されることを確認
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseCount('tasks', 1);
    }

    /**
     * 無効なステータス値でバリデーションエラーが発生することをテストする
     * - ステータスは0,1,2のみ許可されることを確認
     * - 範囲外の値（3）が拒否されることを確認
     */
    public function test_store_fails_with_invalid_status()
    {
        // Arrange: 無効なステータス値を含むタスクデータを準備
        $taskData = [
            'title' => 'タスク',
            'status' => 3, // 無効な値
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tasks', $taskData);

        // Assert: バリデーションエラーの確認
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status'])
            ->assertJsonFragment([
                'status' => ['ステータスは0,1,2のいずれかである必要があります']
            ]);

        $this->assertDatabaseCount('tasks', 0);
    }

    /**
     * ステータスが文字列の場合にバリデーションエラーが発生することをテストする
     * - ステータスは整数である必要があることを確認
     */
    public function test_store_fails_with_string_status()
    {
        // Arrange: 文字列のステータスを含むタスクデータを準備
        $taskData = [
            'title' => 'タスク',
            'status' => 'pending', // 文字列は不可
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tasks', $taskData);

        // Assert: バリデーションエラーの確認
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * ステータスが完了の場合にcompleted_atが自動設定されることをテストする
     * - ビジネスロジックの確認
     * - completed_atが現在時刻に設定されることを確認
     */
    public function test_store_sets_completed_at_when_status_is_completed()
    {
        // Arrange: 完了ステータスのタスクデータを準備
        $taskData = [
            'title' => '完了タスク',
            'status' => Task::STATUS_COMPLETED,
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tasks', $taskData);

        // Assert: レスポンスの検証
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        // データベースでcompleted_atが設定されていることを確認
        $task = Task::first();
        $this->assertNotNull($task->completed_at);
        $this->assertEquals(Task::STATUS_COMPLETED, $task->getAttributes()['status']);
    }

    /**
     * ステータスが完了以外の場合にcompleted_atがnullになることをテストする
     * - ビジネスロジックの確認
     * - completed_atがnullであることを確認
     */
    public function test_store_does_not_set_completed_at_when_status_is_not_completed()
    {
        // Arrange: 未完了ステータスのタスクデータを準備
        $taskData = [
            'title' => '未完了タスク',
            'status' => Task::STATUS_PENDING,
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tasks', $taskData);

        // Assert: レスポンスの検証
        $response->assertStatus(201);

        // データベースでcompleted_atがnullであることを確認
        $task = Task::first();
        $this->assertNull($task->completed_at);
    }

    /**
     * 無効な日付形式でバリデーションエラーが発生することをテストする
     * - 日付フォーマットの検証
     */
    public function test_store_fails_with_invalid_date_format()
    {
        // Arrange: 無効な日付形式を含むタスクデータを準備
        $taskData = [
            'title' => 'タスク',
            'due_date' => 'invalid-date',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tasks', $taskData);

        // Assert: バリデーションエラーの確認
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date'])
            ->assertJsonFragment([
                'due_date' => ['期限日は有効な日付形式で入力してください']
            ]);

        $this->assertDatabaseCount('tasks', 0);
    }

    /**
     * 各ステータス値でタスクを作成できることをテストする
     * - 全てのステータス値（0,1,2）が正しく処理されることを確認
     */
    public function test_store_creates_task_with_each_status()
    {
        // Arrange & Act & Assert: 各ステータスでタスクを作成
        $statuses = [
            Task::STATUS_PENDING,
            Task::STATUS_IN_PROGRESS,
            Task::STATUS_COMPLETED,
        ];

        foreach ($statuses as $status) {
            $taskData = [
                'title' => "タスク - ステータス {$status}",
                'status' => $status,
            ];

            $response = $this->postJson('/api/tasks', $taskData);

            $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                ]);
        }

        // 3つのタスクが作成されたことを確認
        $this->assertDatabaseCount('tasks', 3);
    }

    /**
     * レスポンスにステータスが文字列として含まれることをテストする
     * - アクセサの動作確認
     * - ステータスが"pending"などの文字列で返されることを確認
     */
    public function test_store_response_includes_status_as_string()
    {
        // Arrange: タスクデータを準備
        $taskData = [
            'title' => 'タスク',
            'status' => Task::STATUS_IN_PROGRESS,
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tasks', $taskData);

        // Assert: ステータスが文字列として返されることを確認
        $response->assertStatus(201)
            ->assertJsonFragment([
                'status' => 'in_progress'
            ]);
    }

    /**
     * 複数のバリデーションエラーが同時に返されることをテストする
     * - 複数のフィールドでエラーがある場合の動作を確認
     */
    public function test_store_returns_multiple_validation_errors()
    {
        // Arrange: 複数のバリデーションエラーを含むデータを準備
        $taskData = [
            // titleを欠く
            'status' => 999, // 無効なステータス
            'due_date' => 'not-a-date', // 無効な日付
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tasks', $taskData);

        // Assert: 複数のバリデーションエラーの確認
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'status', 'due_date']);

        $this->assertDatabaseCount('tasks', 0);
    }

    /**
     * descriptionに長いテキストを設定できることをテストする
     * - 大きなテキストフィールドの処理を確認
     */
    public function test_store_creates_task_with_long_description()
    {
        // Arrange: 長い説明を持つタスクデータを準備
        $longDescription = str_repeat('これは長い説明です。', 100);
        $taskData = [
            'title' => 'タスク',
            'description' => $longDescription,
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tasks', $taskData);

        // Assert: 正常に作成されることを確認
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'タスク',
            'description' => $longDescription,
        ]);
    }
}
