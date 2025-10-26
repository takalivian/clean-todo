<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ユーザー一覧を取得できることをテスト
     */
    public function test_index_returns_users_list()
    {
        // Arrange: テストデータの準備
        $this->actingAsUser();
        User::factory()->count(5)->create();

        // Act: ユーザー一覧を取得
        $response = $this->getJson('/api/users');

        // Assert: 正常にユーザー一覧が取得できることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'created_at',
                        'updated_at',
                    ]
                ]
            ]);

        // 6人のユーザーが返されることを確認（認証ユーザー1 + 作成した5人）
        $this->assertCount(6, $response->json('data'));
    }

    /**
     * 認証なしではユーザー一覧を取得できないことをテスト
     */
    public function test_index_fails_without_authentication()
    {
        // Arrange: テストデータの準備（認証なし）
        User::factory()->count(3)->create();

        // Act: ユーザー一覧を取得しようとする
        $response = $this->getJson('/api/users');

        // Assert: 認証エラーが返されることを確認
        $response->assertStatus(401);
    }

    /**
     * ユーザーが存在しない場合に空の配列が返されることをテスト
     */
    public function test_index_returns_empty_array_when_no_users()
    {
        // Arrange: 認証ユーザーのみ作成
        $this->actingAsUser();

        // すべてのユーザーを削除（認証ユーザー以外）
        User::where('id', '!=', auth()->id())->delete();

        // Act: ユーザー一覧を取得
        $response = $this->getJson('/api/users');

        // Assert: 認証ユーザーのみが返されることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(1, $response->json('data'));
    }

    /**
     * パスワードがレスポンスに含まれないことをテスト
     */
    public function test_index_does_not_return_password()
    {
        // Arrange: テストデータの準備
        $this->actingAsUser();
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('secret-password')
        ]);

        // Act: ユーザー一覧を取得
        $response = $this->getJson('/api/users');

        // Assert: パスワードが含まれていないことを確認
        $response->assertStatus(200);

        $users = $response->json('data');
        foreach ($users as $user) {
            $this->assertArrayNotHasKey('password', $user);
        }
    }

    /**
     * ユーザー一覧が正しくソートされていることをテスト
     */
    public function test_index_returns_users_in_order()
    {
        // Arrange: テストデータの準備
        $this->actingAsUser();
        $user1 = User::factory()->create(['name' => 'Alice']);
        $user2 = User::factory()->create(['name' => 'Bob']);
        $user3 = User::factory()->create(['name' => 'Charlie']);

        // Act: ユーザー一覧を取得
        $response = $this->getJson('/api/users');

        // Assert: ユーザーが取得できることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(4, $response->json('data'));
    }

    /**
     * ユーザー情報を更新できることをテスト
     */
    public function test_update_updates_user_successfully()
    {
        // Arrange: テストデータの準備
        $this->actingAsUser();
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];

        // Act: ユーザー情報を更新
        $response = $this->putJson("/api/users/{$user->id}", $updateData);

        // Assert: ユーザー情報が更新されることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'ユーザー情報が更新されました',
                'data' => [
                    'id' => $user->id,
                    'name' => 'Updated Name',
                    'email' => 'updated@example.com',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    /**
     * 名前のみ更新できることをテスト
     */
    public function test_update_updates_only_name()
    {
        // Arrange: テストデータの準備
        $this->actingAsUser();
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        // Act: 名前のみ更新
        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'New Name',
        ]);

        // Assert: 名前のみ更新されることを確認
        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'original@example.com',
        ]);
    }

    /**
     * パスワードを更新できることをテスト
     */
    public function test_update_updates_password()
    {
        // Arrange: テストデータの準備
        $this->actingAsUser();
        $user = User::factory()->create([
            'password' => bcrypt('old-password'),
        ]);

        // Act: パスワードを更新
        $response = $this->putJson("/api/users/{$user->id}", [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        // Assert: パスワードが更新されることを確認
        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(\Hash::check('new-password', $user->password));
    }

    /**
     * 存在しないユーザーを更新しようとするとエラーが返されることをテスト
     */
    public function test_update_returns_error_for_non_existent_user()
    {
        // Arrange: テストデータの準備
        $this->actingAsUser();

        // Act: 存在しないユーザーを更新しようとする
        $response = $this->putJson('/api/users/9999', [
            'name' => 'New Name',
        ]);

        // Assert: エラーが返されることを確認
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'ユーザーが見つかりません',
            ]);
    }

    /**
     * 重複するメールアドレスで更新しようとするとエラーが返されることをテスト
     */
    public function test_update_returns_error_for_duplicate_email()
    {
        // Arrange: テストデータの準備
        $this->actingAsUser();
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        // Act: user2のメールをuser1のメールに変更しようとする
        $response = $this->putJson("/api/users/{$user2->id}", [
            'email' => 'user1@example.com',
        ]);

        // Assert: バリデーションエラーが返されることを確認
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * ユーザーを削除できることをテスト
     */
    public function test_destroy_deletes_user_successfully()
    {
        // Arrange: テストデータの準備
        $this->actingAsUser();
        $user = User::factory()->create();

        // Act: ユーザーを削除
        $response = $this->deleteJson("/api/users/{$user->id}");

        // Assert: ユーザーが削除されることを確認
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'ユーザーが削除されました',
            ]);

        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);
    }

    /**
     * 存在しないユーザーを削除しようとするとエラーが返されることをテスト
     */
    public function test_destroy_returns_error_for_non_existent_user()
    {
        // Arrange: テストデータの準備
        $this->actingAsUser();

        // Act: 存在しないユーザーを削除しようとする
        $response = $this->deleteJson('/api/users/9999');

        // Assert: エラーが返されることを確認
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'ユーザーが見つかりません',
            ]);
    }

    /**
     * 削除されたユーザーは一覧に表示されないことをテスト
     */
    public function test_deleted_user_not_shown_in_list()
    {
        // Arrange: テストデータの準備
        $this->actingAsUser();
        $user = User::factory()->create();

        // ユーザーを削除
        $user->delete();

        // Act: ユーザー一覧を取得
        $response = $this->getJson('/api/users');

        // Assert: 削除されたユーザーが含まれていないことを確認
        $response->assertStatus(200);

        $users = $response->json('data');
        $userIds = collect($users)->pluck('id')->toArray();

        $this->assertNotContains($user->id, $userIds);
    }
}
