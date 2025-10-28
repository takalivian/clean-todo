<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Tag;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTagTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->actingAsUser();
        $this->task = Task::factory()->create(['user_id' => $this->user->id]);
    }

    /**
     * タスクにタグを付与できることをテストする
     * - 複数のタグを一度に付与できることを確認
     * - データベースに正しく保存されることを確認
     */
    public function test_attach_tags_adds_tags_to_task()
    {
        // Arrange: 3つのタグを準備
        $tags = Tag::factory()->count(3)->create(['user_id' => $this->user->id]);

        // Act: タグを付与
        $response = $this->postJson("/api/tasks/{$this->task->id}/tags/attach", [
            'tag_ids' => $tags->pluck('id')->toArray(),
        ]);

        // Assert: レスポンスの検証
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'タグを付与しました',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'tags' => [
                        '*' => ['id', 'name']
                    ]
                ]
            ]);

        // データベースに紐付けが存在することを確認
        foreach ($tags as $tag) {
            $this->assertDatabaseHas('task_tag', [
                'task_id' => $this->task->id,
                'tag_id' => $tag->id,
            ]);
        }

        // タスクに3つのタグが付いていることを確認
        $this->assertEquals(3, $this->task->fresh()->tags()->count());
    }

    /**
     * 既に付いているタグを再度付けてもエラーにならないことをテストする
     * - 重複を許可しないがエラーにもならないことを確認
     * - データベース上では1つのみであることを確認
     */
    public function test_attach_tags_does_not_error_when_tag_already_attached()
    {
        // Arrange: タグを1つ作成し、既に付与しておく
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);
        $this->task->tags()->attach($tag->id);

        // Act: 同じタグをもう一度付与
        $response = $this->postJson("/api/tasks/{$this->task->id}/tags/attach", [
            'tag_ids' => [$tag->id],
        ]);

        // Assert: 成功レスポンス
        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // タグは1つだけであることを確認（重複していない）
        $this->assertEquals(1, $this->task->fresh()->tags()->count());
    }

    /**
     * タスクからタグを削除できることをテストする
     * - 指定したタグのみが削除されることを確認
     * - 他のタグは残ることを確認
     */
    public function test_detach_tags_removes_specific_tags_from_task()
    {
        // Arrange: 3つのタグを付与
        $tags = Tag::factory()->count(3)->create(['user_id' => $this->user->id]);
        $this->task->tags()->attach($tags->pluck('id')->toArray());

        // Act: 2つのタグを削除
        $tagsToDetach = $tags->take(2);
        $response = $this->postJson("/api/tasks/{$this->task->id}/tags/detach", [
            'tag_ids' => $tagsToDetach->pluck('id')->toArray(),
        ]);

        // Assert: レスポンスの検証
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'タグを削除しました',
            ]);

        // 削除されたタグが存在しないことを確認
        foreach ($tagsToDetach as $tag) {
            $this->assertDatabaseMissing('task_tag', [
                'task_id' => $this->task->id,
                'tag_id' => $tag->id,
            ]);
        }

        // 1つのタグだけ残っていることを確認
        $this->assertEquals(1, $this->task->fresh()->tags()->count());
    }

    /**
     * 全てのタグを削除できることをテストする
     * - タスクからタグが全て削除されることを確認
     */
    public function test_detach_tags_removes_all_tags_from_task()
    {
        // Arrange: 3つのタグを付与
        $tags = Tag::factory()->count(3)->create(['user_id' => $this->user->id]);
        $this->task->tags()->attach($tags->pluck('id')->toArray());

        // Act: 全てのタグを削除
        $response = $this->postJson("/api/tasks/{$this->task->id}/tags/detach", [
            'tag_ids' => $tags->pluck('id')->toArray(),
        ]);

        // Assert: レスポンスの検証
        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // 全てのタグが削除されていることを確認
        $this->assertEquals(0, $this->task->fresh()->tags()->count());
    }

    /**
     * 存在しないタスクにタグを付けようとすると404エラーになることをテストする
     */
    public function test_attach_tags_fails_with_non_existent_task()
    {
        // Arrange: 存在しないタスクID
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);

        // Act: 存在しないタスクにタグを付与
        $response = $this->postJson('/api/tasks/99999/tags/attach', [
            'tag_ids' => [$tag->id],
        ]);

        // Assert: エラーレスポンス
        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'タスクが見つかりません');
    }

    /**
     * 削除済みタスクにタグを付けようとするとエラーになることをテストする
     * - 論理削除されたタスクへの操作が拒否されることを確認
     */
    public function test_attach_tags_fails_with_deleted_task()
    {
        // Arrange: タスクを論理削除
        $tag = Tag::factory()->create(['user_id' => $this->user->id]);
        $this->task->delete();

        // Act: 削除済みタスクにタグを付与
        $response = $this->postJson("/api/tasks/{$this->task->id}/tags/attach", [
            'tag_ids' => [$tag->id],
        ]);

        // Assert: エラーレスポンス
        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', '削除済みのタスクにはタグを付けられません');
    }

    /**
     * tag_idsが空の場合エラーになることをテストする
     * - 必須パラメータのチェック
     */
    public function test_attach_tags_fails_when_tag_ids_empty()
    {
        // Act: 空のtag_idsで実行
        $response = $this->postJson("/api/tasks/{$this->task->id}/tags/attach", [
            'tag_ids' => [],
        ]);

        // Assert: エラーレスポンス
        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'タグIDが指定されていません');
    }

    /**
     * 認証なしではタグ操作できないことをテストする
     * - 認証が必須であることを確認
     */
    public function test_tag_operations_require_authentication()
    {
        // Arrange: 認証をクリア
        $this->app['auth']->forgetGuards();
        $tag = Tag::factory()->create();

        // Act & Assert: attach
        $response = $this->postJson("/api/tasks/{$this->task->id}/tags/attach", [
            'tag_ids' => [$tag->id],
        ]);
        $response->assertStatus(401);

        // Act & Assert: detach
        $response = $this->postJson("/api/tasks/{$this->task->id}/tags/detach", [
            'tag_ids' => [$tag->id],
        ]);
        $response->assertStatus(401);
    }

    /**
     * タスク取得時にタグ情報も含まれることをテストする
     * - Eager Loadingが正しく動作することを確認
     */
    public function test_task_retrieval_includes_tags()
    {
        // Arrange: タスクに2つのタグを付与
        $tags = Tag::factory()->count(2)->create(['user_id' => $this->user->id]);
        $this->task->tags()->attach($tags->pluck('id')->toArray());

        // Act: タスクを取得
        $response = $this->getJson("/api/tasks/{$this->task->id}");

        // Assert: レスポンスにタグが含まれることを確認
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'tags' => [
                        '*' => ['id', 'name']
                    ]
                ]
            ]);

        $responseTags = $response->json('data.tags');
        $this->assertCount(2, $responseTags);
    }

    /**
     * 複数回に分けてタグを追加できることをテストする
     * - 既存のタグを保持したまま新しいタグを追加できることを確認
     */
    public function test_attach_tags_multiple_times_accumulates_tags()
    {
        // Arrange: 2組のタグを準備
        $firstTags = Tag::factory()->count(2)->create(['user_id' => $this->user->id]);
        $secondTags = Tag::factory()->count(2)->create(['user_id' => $this->user->id]);

        // Act: 1回目のタグ付与
        $this->postJson("/api/tasks/{$this->task->id}/tags/attach", [
            'tag_ids' => $firstTags->pluck('id')->toArray(),
        ])->assertStatus(200);

        // Act: 2回目のタグ付与
        $this->postJson("/api/tasks/{$this->task->id}/tags/attach", [
            'tag_ids' => $secondTags->pluck('id')->toArray(),
        ])->assertStatus(200);

        // Assert: 合計4つのタグが付いていることを確認
        $this->assertEquals(4, $this->task->fresh()->tags()->count());
    }
}
