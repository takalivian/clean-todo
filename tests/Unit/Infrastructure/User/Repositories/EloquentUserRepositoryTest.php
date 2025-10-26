<?php

namespace Tests\Unit\Infrastructure\User\Repositories;

use App\Infrastructure\User\Repositories\EloquentUserRepository;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentUserRepository();
    }

    /**
     * 全ユーザーを取得できる
     */
    public function test_find_all_returns_all_users()
    {
        // Arrange
        User::factory()->create(['name' => 'User 1']);
        User::factory()->create(['name' => 'User 2']);
        User::factory()->create(['name' => 'User 3']);

        // Act
        $result = $this->repository->findAll();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);
        $this->assertContains('User 1', $result->pluck('name')->toArray());
        $this->assertContains('User 2', $result->pluck('name')->toArray());
        $this->assertContains('User 3', $result->pluck('name')->toArray());
    }

    /**
     * ユーザーが存在しない場合は空のコレクションを返す
     */
    public function test_find_all_returns_empty_collection_when_no_users()
    {
        // Act
        $result = $this->repository->findAll();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    /**
     * IDでユーザーを取得できる
     */
    public function test_find_by_id_returns_user()
    {
        // Arrange
        $user = User::factory()->create(['name' => 'Test User']);

        // Act
        $result = $this->repository->findById($user->id);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);
        $this->assertEquals('Test User', $result->name);
    }

    /**
     * 存在しないIDの場合はnullを返す
     */
    public function test_find_by_id_returns_null_when_not_found()
    {
        // Act
        $result = $this->repository->findById(9999);

        // Assert
        $this->assertNull($result);
    }

    /**
     * メールアドレスでユーザーを取得できる
     */
    public function test_find_by_email_returns_user()
    {
        // Arrange
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        // Act
        $result = $this->repository->findByEmail('test@example.com');

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('test@example.com', $result->email);
        $this->assertEquals('Test User', $result->name);
    }

    /**
     * 存在しないメールアドレスの場合はnullを返す
     */
    public function test_find_by_email_returns_null_when_not_found()
    {
        // Act
        $result = $this->repository->findByEmail('nonexistent@example.com');

        // Assert
        $this->assertNull($result);
    }

    /**
     * 新しいユーザーを作成できる
     */
    public function test_create_creates_user_successfully()
    {
        // Arrange
        $data = [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => bcrypt('password123'),
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('New User', $result->name);
        $this->assertEquals('new@example.com', $result->email);
        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'new@example.com',
        ]);
    }

    /**
     * ユーザーを更新できる
     */
    public function test_update_updates_user_successfully()
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];

        // Act
        $result = $this->repository->update($user, $updateData);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('Updated Name', $result->name);
        $this->assertEquals('updated@example.com', $result->email);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    /**
     * 一部のフィールドのみ更新できる
     */
    public function test_update_updates_only_specified_fields()
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $updateData = [
            'name' => 'Updated Name',
        ];

        // Act
        $result = $this->repository->update($user, $updateData);

        // Assert
        $this->assertEquals('Updated Name', $result->name);
        $this->assertEquals('original@example.com', $result->email);
    }

    /**
     * ユーザーを削除できる（ソフトデリート）
     */
    public function test_delete_soft_deletes_user()
    {
        // Arrange
        $user = User::factory()->create(['name' => 'User to Delete']);

        // Act
        $result = $this->repository->delete($user);

        // Assert
        $this->assertTrue($result);
        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);
    }

    /**
     * 削除されたユーザーは通常の取得では取得されない
     */
    public function test_deleted_user_not_returned_in_find_all()
    {
        // Arrange
        User::factory()->create(['name' => 'Active User']);
        $deletedUser = User::factory()->create(['name' => 'Deleted User']);
        $deletedUser->delete();

        // Act
        $result = $this->repository->findAll();

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('Active User', $result->first()->name);
    }
}
