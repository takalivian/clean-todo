<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskStatisticsByUserTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->actingAsUser();
    }

    /**
     * デフォルト（Top 5）でユーザー別統計を取得できることをテストする
     * - レスポンス構造の確認
     * - デフォルトで5件返されることを確認
     */
    public function test_get_statistics_returns_top_5_users_by_default()
    {
        // Arrange: 10人のユーザーにそれぞれタスクを作成
        $users = User::factory()->count(10)->create();

        foreach ($users as $index => $user) {
            // タスク数を1〜10件でばらつかせる
            Task::factory()->count($index + 1)->create(['user_id' => $user->id]);
        }

        // Act: 統計APIを呼び出し
        $response = $this->getJson('/api/tasks/statistics/by-user');

        // Assert: レスポンスの検証
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'ユーザー別タスク統計を取得しました',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'user' => ['id', 'name', 'email'],
                        'task_count',
                        'recent_tasks',
                    ]
                ]
            ]);

        // データが5件であることを確認
        $this->assertCount(5, $response->json('data'));

        // タスク数が降順であることを確認
        $data = $response->json('data');
        for ($i = 0; $i < count($data) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $data[$i + 1]['task_count'],
                $data[$i]['task_count']
            );
        }
    }

    /**
     * limitパラメータで取得件数を変更できることをテストする
     * - limit=3で3件返されることを確認
     */
    public function test_get_statistics_with_custom_limit()
    {
        // Arrange: 5人のユーザーにタスクを作成
        $users = User::factory()->count(5)->create();

        foreach ($users as $index => $user) {
            Task::factory()->count($index + 1)->create(['user_id' => $user->id]);
        }

        // Act: limit=3で統計APIを呼び出し
        $response = $this->getJson('/api/tasks/statistics/by-user?limit=3');

        // Assert: レスポンスの検証
        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    /**
     * limit=1でトップユーザーのみ取得できることをテストする
     */
    public function test_get_statistics_with_limit_one()
    {
        // Arrange: 3人のユーザーにタスクを作成
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        Task::factory()->count(5)->create(['user_id' => $user1->id]);
        Task::factory()->count(10)->create(['user_id' => $user2->id]); // 最多
        Task::factory()->count(3)->create(['user_id' => $user3->id]);

        // Act: limit=1で統計APIを呼び出し
        $response = $this->getJson('/api/tasks/statistics/by-user?limit=1');

        // Assert: レスポンスの検証
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals($user2->id, $data[0]['user']['id']);
        $this->assertEquals(10, $data[0]['task_count']);
    }

    /**
     * タスクが存在しない場合でも正常に動作することをテストする
     * - 空の配列が返されることを確認
     */
    public function test_get_statistics_returns_empty_when_no_tasks()
    {
        // Arrange: タスクを作成しない

        // Act: 統計APIを呼び出し
        $response = $this->getJson('/api/tasks/statistics/by-user');

        // Assert: 空のデータが返される
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => []
            ]);
    }

    /**
     * 同じタスク数のユーザーが複数いる場合の動作をテストする
     * - 正しく全員が返されることを確認
     */
    public function test_get_statistics_handles_users_with_same_task_count()
    {
        // Arrange: 3人のユーザーに同じ数のタスクを作成
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            Task::factory()->count(5)->create(['user_id' => $user->id]);
        }

        // Act: 統計APIを呼び出し
        $response = $this->getJson('/api/tasks/statistics/by-user');

        // Assert: 3人全員が返される
        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));

        // 全員タスク数が5であることを確認
        foreach ($response->json('data') as $item) {
            $this->assertEquals(5, $item['task_count']);
        }
    }

    /**
     * 大量のユーザーが存在する場合でも正しく動作することをテストする
     * - limitが正しく適用されることを確認
     */
    public function test_get_statistics_with_many_users()
    {
        // Arrange: 20人のユーザーにタスクを作成
        $users = User::factory()->count(20)->create();

        foreach ($users as $index => $user) {
            Task::factory()->count(rand(1, 50))->create(['user_id' => $user->id]);
        }

        // Act: limit=10で統計APIを呼び出し
        $response = $this->getJson('/api/tasks/statistics/by-user?limit=10');

        // Assert: 10件のみ返される
        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));

        // タスク数が降順であることを確認
        $data = $response->json('data');
        for ($i = 0; $i < count($data) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $data[$i + 1]['task_count'],
                $data[$i]['task_count']
            );
        }
    }

    /**
     * 認証が必須であることをテストする
     * - 認証なしでアクセスすると401エラー
     */
    public function test_get_statistics_requires_authentication()
    {
        // Arrange: 認証をクリア
        $this->app['auth']->forgetGuards();

        // Act: 認証なしで統計APIを呼び出し
        $response = $this->getJson('/api/tasks/statistics/by-user');

        // Assert: 認証エラー
        $response->assertStatus(401);
    }

    /**
     * 削除済みタスクも集計に含まれることをテストする
     * - SoftDeletesを使用しているため、削除済みもカウントされる
     */
    public function test_get_statistics_includes_all_tasks()
    {
        // Arrange: ユーザーとタスクを作成、一部を削除
        $user = User::factory()->create();

        Task::factory()->count(5)->create(['user_id' => $user->id]);
        $deletedTasks = Task::factory()->count(3)->create(['user_id' => $user->id]);
        foreach ($deletedTasks as $task) {
            $task->delete(); // 論理削除
        }

        // Act: 統計APIを呼び出し
        $response = $this->getJson('/api/tasks/statistics/by-user');

        // Assert: 削除済みを含めた8件がカウントされる
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals($user->id, $data[0]['user']['id']);
        $this->assertEquals(8, $data[0]['task_count']);
    }

    /**
     * ユーザー情報が正しく含まれることをテストする
     * - id, name, emailが含まれる
     */
    public function test_get_statistics_includes_user_information()
    {
        // Arrange: ユーザーとタスクを作成
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Task::factory()->count(3)->create(['user_id' => $user->id]);

        // Act: 統計APIを呼び出し
        $response = $this->getJson('/api/tasks/statistics/by-user');

        // Assert: ユーザー情報が含まれる
        $response->assertStatus(200);
        $data = $response->json('data.0');

        $this->assertEquals($user->id, $data['user']['id']);
        $this->assertEquals('Test User', $data['user']['name']);
        $this->assertEquals('test@example.com', $data['user']['email']);
    }

    /**
     * 無効なlimitパラメータでもエラーにならないことをテストする
     * - 不正な値の場合はデフォルト値（5）が使用される
     */
    public function test_get_statistics_handles_invalid_limit()
    {
        // Arrange: 10人のユーザーにタスクを作成
        $users = User::factory()->count(10)->create();

        foreach ($users as $index => $user) {
            Task::factory()->count($index + 1)->create(['user_id' => $user->id]);
        }

        // Act: 無効なlimitで統計APIを呼び出し
        $response = $this->getJson('/api/tasks/statistics/by-user?limit=invalid');

        // Assert: エラーにならず、デフォルト値で動作
        $response->assertStatus(200);
        // limitが無効な場合、DTOでデフォルト5が使われる
        $this->assertCount(5, $response->json('data'));
    }
}
