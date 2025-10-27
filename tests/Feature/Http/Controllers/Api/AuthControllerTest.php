<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ユーザー登録が正常に動作することをテストする
     */
    public function test_register_creates_user_successfully()
    {
        // Arrange: 登録データを準備
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/register', $userData);

        // Assert: レスポンスの検証
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'ユーザー登録が完了しました',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                ],
            ]);

        // データベースにユーザーが作成されたことを確認
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);
    }

    /**
     * メールアドレスが重複している場合にエラーが返されることをテストする
     */
    public function test_register_fails_with_duplicate_email()
    {
        // Arrange: 既存ユーザーを作成
        User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $userData = [
            'name' => 'Test User 2',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/register', $userData);

        // Assert: バリデーションエラーが返される
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * パスワード確認が一致しない場合にエラーが返されることをテストする
     */
    public function test_register_fails_when_password_confirmation_does_not_match()
    {
        // Arrange: パスワード確認が一致しないデータ
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different_password',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/register', $userData);

        // Assert: バリデーションエラーが返される
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * ログインが正常に動作することをテストする
     */
    public function test_login_authenticates_user_successfully()
    {
        // Arrange: ユーザーを作成
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $credentials = [
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/login', $credentials);

        // Assert: レスポンスの検証
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'ログインしました',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                ],
            ]);
    }

    /**
     * 間違ったパスワードでログインできないことをテストする
     */
    public function test_login_fails_with_incorrect_password()
    {
        // Arrange: ユーザーを作成
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $credentials = [
            'email' => 'test@example.com',
            'password' => 'wrong_password',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/login', $credentials);

        // Assert: エラーが返される
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'メールアドレスまたはパスワードが正しくありません。',
            ]);
    }

    /**
     * 存在しないメールアドレスでログインできないことをテストする
     */
    public function test_login_fails_with_non_existent_email()
    {
        // Arrange: 存在しないメールアドレス
        $credentials = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/login', $credentials);

        // Assert: エラーが返される
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'メールアドレスまたはパスワードが正しくありません。',
            ]);
    }

    /**
     * ログアウトが正常に動作することをテストする
     */
    public function test_logout_deletes_current_token()
    {
        // Arrange: ユーザーを作成してログイン
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        // Act: HTTPリクエスト実行（認証トークンを使用）
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        // Assert: レスポンスの検証
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'ログアウトしました',
            ]);

        // トークンが削除されたことを確認
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    /**
     * 認証されていない場合にログアウトできないことをテストする
     */
    public function test_logout_fails_without_authentication()
    {
        // Act: HTTPリクエスト実行（トークンなし）
        $response = $this->postJson('/api/logout');

        // Assert: 認証エラーが返される
        $response->assertStatus(401);
    }

    /**
     * 現在のユーザー情報を取得できることをテストする
     */
    public function test_user_returns_authenticated_user_information()
    {
        // Arrange: ユーザーを作成してログイン
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $token = $user->createToken('test_token')->plainTextToken;

        // Act: HTTPリクエスト実行（認証トークンを使用）
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/user');

        // Assert: レスポンスの検証
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ],
            ]);
    }

    /**
     * 認証されていない場合にユーザー情報を取得できないことをテストする
     */
    public function test_user_fails_without_authentication()
    {
        // Act: HTTPリクエスト実行（トークンなし）
        $response = $this->getJson('/api/user');

        // Assert: 認証エラーが返される
        $response->assertStatus(401);
    }

    /**
     * 無効なトークンでアクセスできないことをテストする
     */
    public function test_user_fails_with_invalid_token()
    {
        // Act: HTTPリクエスト実行（無効なトークン）
        $response = $this->withHeader('Authorization', 'Bearer invalid_token')
            ->getJson('/api/user');

        // Assert: 認証エラーが返される
        $response->assertStatus(401);
    }

    /**
     * パスワードが8文字未満の場合にエラーが返されることをテストする
     */
    public function test_register_fails_with_short_password()
    {
        // Arrange: パスワードが短いデータ
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/register', $userData);

        // Assert: バリデーションエラーが返される
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * 必須フィールドが欠けている場合にエラーが返されることをテストする
     */
    public function test_register_fails_with_missing_required_fields()
    {
        // Arrange: 必須フィールドが欠けているデータ
        $userData = [
            'email' => 'test@example.com',
        ];

        // Act: HTTPリクエスト実行
        $response = $this->postJson('/api/register', $userData);

        // Assert: バリデーションエラーが返される
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'password']);
    }
}
