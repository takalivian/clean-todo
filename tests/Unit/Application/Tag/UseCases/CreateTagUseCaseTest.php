<?php

namespace Tests\Unit\Application\Tag\UseCases;

use App\Application\Tag\DTOs\CreateTagDto;
use App\Application\Tag\UseCases\CreateTagUseCase;
use App\Domain\Tag\Repositories\TagRepositoryInterface;
use App\Models\Tag;
use Mockery;
use PHPUnit\Framework\TestCase;

class CreateTagUseCaseTest extends TestCase
{
    private TagRepositoryInterface $mockRepository;
    private CreateTagUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = Mockery::mock(TagRepositoryInterface::class);
        $this->useCase = new CreateTagUseCase($this->mockRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 通常のタグ作成をテストする
     * - リポジトリのcreateメソッドが正しいデータで呼ばれることを確認
     * - 作成されたタグが返されることを確認
     */
    public function test_execute_creates_tag_successfully()
    {
        // Arrange: テストデータの準備
        $dto = new CreateTagDto(
            user_id: 1,
            name: 'テストタグ'
        );

        $expectedData = [
            'user_id' => 1,
            'name' => 'テストタグ',
        ];

        $mockTag = Mockery::mock(Tag::class);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('create')
            ->with(Mockery::on(function ($data) use ($expectedData) {
                $this->assertEquals($expectedData['user_id'], $data['user_id']);
                $this->assertEquals($expectedData['name'], $data['name']);
                return true;
            }))
            ->once()
            ->andReturn($mockTag);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertSame($mockTag, $result);
    }

    /**
     * DTOから正しく配列に変換されることをテストする
     * - toArray()メソッドが正しく呼ばれることを確認
     */
    public function test_execute_calls_repository_with_dto_array()
    {
        // Arrange: テストデータの準備
        $dto = new CreateTagDto(
            user_id: 123,
            name: '重要タグ'
        );

        $mockTag = Mockery::mock(Tag::class);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('create')
            ->with([
                'user_id' => 123,
                'name' => '重要タグ',
            ])
            ->once()
            ->andReturn($mockTag);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertSame($mockTag, $result);
    }

    /**
     * リポジトリから返されたTagオブジェクトがそのまま返されることをテストする
     * - UseCaseがリポジトリの戻り値を変更しないことを確認
     */
    public function test_execute_returns_tag_from_repository()
    {
        // Arrange: テストデータの準備
        $dto = new CreateTagDto(
            user_id: 1,
            name: 'タグ'
        );

        $expectedTag = new Tag(['id' => 999, 'name' => 'タグ']);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($expectedTag);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: リポジトリから返されたタグがそのまま返されることを確認
        $this->assertSame($expectedTag, $result);
    }

    /**
     * 日本語のタグ名でタグを作成できることをテストする
     * - マルチバイト文字の処理を確認
     */
    public function test_execute_creates_tag_with_japanese_name()
    {
        // Arrange: 日本語のタグ名を準備
        $dto = new CreateTagDto(
            user_id: 1,
            name: '日本語タグ名'
        );

        $mockTag = Mockery::mock(Tag::class);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                $this->assertEquals('日本語タグ名', $data['name']);
                return true;
            }))
            ->once()
            ->andReturn($mockTag);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertSame($mockTag, $result);
    }

    /**
     * 長いタグ名でタグを作成できることをテストする
     * - 長い文字列の処理を確認
     */
    public function test_execute_creates_tag_with_long_name()
    {
        // Arrange: 長いタグ名を準備
        $longName = str_repeat('あ', 255);
        $dto = new CreateTagDto(
            user_id: 1,
            name: $longName
        );

        $mockTag = Mockery::mock(Tag::class);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('create')
            ->with(Mockery::on(function ($data) use ($longName) {
                $this->assertEquals($longName, $data['name']);
                $this->assertEquals(255, mb_strlen($data['name']));
                return true;
            }))
            ->once()
            ->andReturn($mockTag);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertSame($mockTag, $result);
    }

    /**
     * 特殊文字を含むタグ名でタグを作成できることをテストする
     * - 特殊文字の処理を確認
     */
    public function test_execute_creates_tag_with_special_characters()
    {
        // Arrange: 特殊文字を含むタグ名を準備
        $dto = new CreateTagDto(
            user_id: 1,
            name: 'タグ-123_ABC!@#'
        );

        $mockTag = Mockery::mock(Tag::class);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                $this->assertEquals('タグ-123_ABC!@#', $data['name']);
                return true;
            }))
            ->once()
            ->andReturn($mockTag);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertSame($mockTag, $result);
    }
}
