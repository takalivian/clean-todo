<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagControllerStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 認証されたユーザーとしてテストを実行
        $this->actingAsUser();
    }

    /**
     * 正常なタグ作成が成功することをテストする
     * - 全フィールドを指定してタグを作成
     * - 成功レスポンス（201）とJSON形式の確認
     * - データベースに正しく保存されることを確認
     */
    public function test_store_creates_tag_successfully()
    {
        // Arrange: タグデータを準備
        $tagData = [
            'name' => 'テストタグ',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tags', $tagData);

        // Assert: レスポンスの検証
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'タグが作成されました',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'user_id',
                    'created_at',
                    'updated_at',
                ]
            ]);

        // データベースに保存されたことを確認
        $this->assertDatabaseHas('tags', [
            'name' => 'テストタグ',
        ]);
    }

    /**
     * nameが欠けている場合にバリデーションエラーが発生することをテストする
     * - 必須フィールドが欠けている場合の動作を検証
     * - 422エラーが返されることを確認
     * - エラーメッセージが日本語で返されることを確認
     */
    public function test_store_fails_without_required_name()
    {
        // Arrange: nameを欠いたタグデータを準備
        $tagData = [];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tags', $tagData);

        // Assert: バリデーションエラーの確認
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name'])
            ->assertJsonFragment([
                'name' => ['タグ名は必須です']
            ]);

        // データベースに保存されていないことを確認
        $this->assertDatabaseCount('tags', 0);
    }

    /**
     * nameが255文字を超える場合にバリデーションエラーが発生することをテストする
     * - 最大文字数制限の検証
     * - 境界値テスト
     */
    public function test_store_fails_when_name_exceeds_max_length()
    {
        // Arrange: 256文字のnameを準備
        $tagData = [
            'name' => str_repeat('あ', 256),
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tags', $tagData);

        // Assert: バリデーションエラーの確認
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name'])
            ->assertJsonFragment([
                'name' => ['タグ名は255文字以内で入力してください']
            ]);

        // データベースに保存されていないことを確認
        $this->assertDatabaseCount('tags', 0);
    }

    /**
     * 255文字ちょうどのnameは許可されることをテストする
     * - 境界値テスト（正常系）
     */
    public function test_store_succeeds_with_max_length_name()
    {
        // Arrange: 255文字のnameを準備
        $tagData = [
            'name' => str_repeat('あ', 255),
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tags', $tagData);

        // Assert: 正常に作成されることを確認
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseCount('tags', 1);
    }

    /**
     * 作成されたタグにユーザーIDが正しく設定されることをテストする
     * - 認証されたユーザーのIDが自動的に設定されることを確認
     */
    public function test_store_sets_authenticated_user_id()
    {
        // Arrange: タグデータを準備
        $tagData = [
            'name' => 'ユーザータグ',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tags', $tagData);

        // Assert: レスポンスの検証
        $response->assertStatus(201);

        // データベースで認証ユーザーのIDが設定されていることを確認
        $tag = Tag::first();
        $this->assertEquals(auth()->id(), $tag->user_id);
    }

    /**
     * 認証なしでタグ作成を試みるとエラーになることをテストする
     * - 認証が必須であることを確認
     */
    public function test_store_fails_without_authentication()
    {
        // Arrange: 認証をクリア
        $this->app['auth']->forgetGuards();

        $tagData = [
            'name' => 'テストタグ',
        ];

        // Act: 認証なしでHTTPリクエスト実行
        $response = $this->postJson('/api/tags', $tagData);

        // Assert: 認証エラーの確認
        $response->assertStatus(401);

        // データベースに保存されていないことを確認
        $this->assertDatabaseCount('tags', 0);
    }

    /**
     * 日本語、英語、数字、記号を含むタグ名で作成できることをテストする
     * - 多様な文字種の処理を確認
     */
    public function test_store_creates_tag_with_various_characters()
    {
        // Arrange: 様々な文字を含むタグ名を準備
        $tagNames = [
            '日本語タグ',
            'English Tag',
            '12345',
            'タグ-123_ABC',
            '重要！',
        ];

        foreach ($tagNames as $tagName) {
            // Act: HTTPリクエスト実行
            $response = $this->postJson('/api/tags', ['name' => $tagName]);

            // Assert: 正常に作成されることを確認
            $response->assertStatus(201);

            $this->assertDatabaseHas('tags', [
                'name' => $tagName,
            ]);
        }

        $this->assertDatabaseCount('tags', count($tagNames));
    }

    /**
     * 同じ名前のタグを複数作成できることをテストする
     * - タグ名にユニーク制約がないことを確認
     * - 異なるユーザーが同じ名前のタグを作成できることを確認
     */
    public function test_store_allows_duplicate_tag_names()
    {
        // Arrange & Act: 同じ名前のタグを2回作成
        $tagData = ['name' => '重複タグ'];

        $response1 = $this->postJson('/api/tags', $tagData);
        $response2 = $this->postJson('/api/tags', $tagData);

        // Assert: 両方とも成功することを確認
        $response1->assertStatus(201);
        $response2->assertStatus(201);

        // データベースに2つのタグが存在することを確認
        $this->assertDatabaseCount('tags', 2);
    }

    /**
     * 空白のみのタグ名でバリデーションエラーが発生することをテストする
     * - 空白のみは許可されないことを確認
     */
    public function test_store_fails_with_whitespace_only_name()
    {
        // Arrange: 空白のみのタグ名を準備
        $tagData = [
            'name' => '   ',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/tags', $tagData);

        // Assert: バリデーションエラーの確認
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('tags', 0);
    }
}
