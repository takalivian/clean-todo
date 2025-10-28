<?php

namespace Tests\Unit\Infrastructure\Tag\Repositories;

use App\Infrastructure\Tag\Repositories\EloquentTagRepository;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentTagRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentTagRepository $repository;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentTagRepository();
        $this->user = User::factory()->create();
    }

    /**
     * タグを作成できることをテストする
     * - create()メソッドが正しくタグを作成することを確認
     * - 作成されたタグがTagモデルのインスタンスであることを確認
     */
    public function test_create_creates_tag_successfully()
    {
        // Arrange: タグデータを準備
        $data = [
            'name' => 'テストタグ',
            'user_id' => $this->user->id,
        ];

        // Act: タグを作成
        $result = $this->repository->create($data);

        // Assert: 結果の検証
        $this->assertInstanceOf(Tag::class, $result);
        $this->assertEquals('テストタグ', $result->name);
        $this->assertEquals($this->user->id, $result->user_id);

        // データベースに保存されていることを確認
        $this->assertDatabaseHas('tags', [
            'name' => 'テストタグ',
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * IDでタグを取得できることをテストする
     * - findById()メソッドが正しくタグを取得することを確認
     * - Eager Loadingでユーザー情報も取得されることを確認
     */
    public function test_find_by_id_returns_tag_with_relations()
    {
        // Arrange: タグを作成
        $tag = Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'テストタグ',
        ]);

        // Act: IDでタグを取得
        $result = $this->repository->findById($tag->id);

        // Assert: 結果の検証
        $this->assertInstanceOf(Tag::class, $result);
        $this->assertEquals($tag->id, $result->id);
        $this->assertEquals('テストタグ', $result->name);

        // リレーションが読み込まれていることを確認
        $this->assertTrue($result->relationLoaded('user'));
        $this->assertTrue($result->relationLoaded('updater'));
    }

    /**
     * 存在しないIDで取得した場合nullが返されることをテストする
     * - findById()メソッドがnullを返すことを確認
     */
    public function test_find_by_id_returns_null_when_not_found()
    {
        // Act: 存在しないIDでタグを取得
        $result = $this->repository->findById(99999);

        // Assert: nullが返されることを確認
        $this->assertNull($result);
    }

    /**
     * 全タグを取得できることをテストする
     * - findAll()メソッドが全てのタグを取得することを確認
     * - Eager Loadingでユーザー情報も取得されることを確認
     */
    public function test_find_all_returns_all_tags()
    {
        // Arrange: 3つのタグを作成
        Tag::factory()->count(3)->create(['user_id' => $this->user->id]);

        // Act: 全タグを取得
        $result = $this->repository->findAll();

        // Assert: 結果の検証
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);

        // 各タグでリレーションが読み込まれていることを確認
        foreach ($result as $tag) {
            $this->assertTrue($tag->relationLoaded('user'));
            $this->assertTrue($tag->relationLoaded('updater'));
        }
    }

    /**
     * タグが存在しない場合の空コレクション返却をテストする
     * - findAll()メソッドが空のコレクションを返すことを確認
     */
    public function test_find_all_returns_empty_collection_when_no_tags()
    {
        // Act: タグが存在しない状態で取得
        $result = $this->repository->findAll();

        // Assert: 空のコレクションが返されることを確認
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);
    }

    /**
     * タグを更新できることをテストする
     * - update()メソッドが正しくタグを更新することを確認
     * - 更新後のタグが返されることを確認
     */
    public function test_update_updates_tag_successfully()
    {
        // Arrange: タグを作成
        $tag = Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => '元のタグ名',
        ]);

        $updateData = [
            'name' => '更新されたタグ名',
            'updated_by' => $this->user->id,
        ];

        // Act: タグを更新
        $result = $this->repository->update($tag, $updateData);

        // Assert: 結果の検証
        $this->assertInstanceOf(Tag::class, $result);
        $this->assertEquals('更新されたタグ名', $result->name);
        $this->assertEquals($this->user->id, $result->updated_by);

        // データベースが更新されていることを確認
        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => '更新されたタグ名',
            'updated_by' => $this->user->id,
        ]);
    }

    /**
     * タグを削除できることをテストする（論理削除）
     * - delete()メソッドが正しく論理削除することを確認
     * - deleted_atカラムが設定されることを確認
     */
    public function test_delete_soft_deletes_tag()
    {
        // Arrange: タグを作成
        $tag = Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => '削除されるタグ',
        ]);

        // Act: タグを削除
        $result = $this->repository->delete($tag);

        // Assert: 削除成功
        $this->assertTrue($result);

        // 論理削除されていることを確認
        $this->assertSoftDeleted('tags', [
            'id' => $tag->id,
        ]);

        // 通常のクエリでは取得できないことを確認
        $this->assertNull(Tag::find($tag->id));

        // 削除済みを含めると取得できることを確認
        $this->assertNotNull(Tag::withTrashed()->find($tag->id));
    }

    /**
     * フィルタ条件付きでタグを取得できることをテストする（キーワード検索）
     * - findAllWithFilter()メソッドがキーワードでフィルタすることを確認
     */
    public function test_find_all_with_filter_filters_by_keyword()
    {
        // Arrange: 複数のタグを作成
        Tag::factory()->create(['user_id' => $this->user->id, 'name' => '重要']);
        Tag::factory()->create(['user_id' => $this->user->id, 'name' => '緊急']);
        Tag::factory()->create(['user_id' => $this->user->id, 'name' => 'バグ']);

        $dto = new \App\Application\Tag\DTOs\GetTagsDto(
            userId: null,
            keyword: '重要',
            sortBy: 'created_at',
            sortDirection: 'desc'
        );

        // Act: キーワードでフィルタ
        $result = $this->repository->findAllWithFilter($dto);

        // Assert: 結果の検証
        $this->assertCount(1, $result);
        $this->assertEquals('重要', $result->first()->name);
    }

    /**
     * フィルタ条件付きでタグを取得できることをテストする（ユーザーIDフィルタ）
     * - findAllWithFilter()メソッドがユーザーIDでフィルタすることを確認
     */
    public function test_find_all_with_filter_filters_by_user_id()
    {
        // Arrange: 2人のユーザーでタグを作成
        $user1 = $this->user;
        $user2 = User::factory()->create();

        Tag::factory()->count(2)->create(['user_id' => $user1->id]);
        Tag::factory()->count(3)->create(['user_id' => $user2->id]);

        $dto = new \App\Application\Tag\DTOs\GetTagsDto(
            userId: $user1->id,
            keyword: null,
            sortBy: 'created_at',
            sortDirection: 'desc'
        );

        // Act: ユーザーIDでフィルタ
        $result = $this->repository->findAllWithFilter($dto);

        // Assert: user1のタグのみが返されることを確認
        $this->assertCount(2, $result);
        foreach ($result as $tag) {
            $this->assertEquals($user1->id, $tag->user_id);
        }
    }

    /**
     * フィルタ条件付きでタグを取得できることをテストする（ソート）
     * - findAllWithFilter()メソッドがソート順を適用することを確認
     */
    public function test_find_all_with_filter_sorts_results()
    {
        // Arrange: 異なる作成日時でタグを作成
        $tag1 = Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'タグ1',
            'created_at' => now()->subDays(3),
        ]);
        $tag2 = Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'タグ2',
            'created_at' => now()->subDays(2),
        ]);
        $tag3 = Tag::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'タグ3',
            'created_at' => now()->subDays(1),
        ]);

        $dto = new \App\Application\Tag\DTOs\GetTagsDto(
            userId: null,
            keyword: null,
            sortBy: 'created_at',
            sortDirection: 'asc'
        );

        // Act: 昇順でソート
        $result = $this->repository->findAllWithFilter($dto);

        // Assert: 昇順で返されることを確認
        $this->assertEquals($tag1->id, $result[0]->id);
        $this->assertEquals($tag2->id, $result[1]->id);
        $this->assertEquals($tag3->id, $result[2]->id);
    }

    /**
     * 複数のフィルタ条件を組み合わせて使用できることをテストする
     * - ユーザーIDとキーワードの両方でフィルタすることを確認
     */
    public function test_find_all_with_filter_combines_multiple_filters()
    {
        // Arrange: 複数のタグを作成
        $user2 = User::factory()->create();

        Tag::factory()->create(['user_id' => $this->user->id, 'name' => '重要タグ']);
        Tag::factory()->create(['user_id' => $this->user->id, 'name' => '通常タグ']);
        Tag::factory()->create(['user_id' => $user2->id, 'name' => '重要タグ2']);

        $dto = new \App\Application\Tag\DTOs\GetTagsDto(
            userId: $this->user->id,
            keyword: '重要',
            sortBy: 'created_at',
            sortDirection: 'desc'
        );

        // Act: 複数条件でフィルタ
        $result = $this->repository->findAllWithFilter($dto);

        // Assert: 条件に合致する1件のみが返されることを確認
        $this->assertCount(1, $result);
        $this->assertEquals('重要タグ', $result->first()->name);
        $this->assertEquals($this->user->id, $result->first()->user_id);
    }
}
