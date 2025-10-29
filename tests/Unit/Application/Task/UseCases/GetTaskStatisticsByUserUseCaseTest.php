<?php

namespace Tests\Unit\Application\Task\UseCases;

use App\Application\Task\DTOs\GetTaskStatisticsByUserDto;
use App\Application\Task\UseCases\GetTaskStatisticsByUserUseCase;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\TestCase;

class GetTaskStatisticsByUserUseCaseTest extends TestCase
{
    private TaskRepositoryInterface $mockRepository;
    private GetTaskStatisticsByUserUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = Mockery::mock(TaskRepositoryInterface::class);
        $this->useCase = new GetTaskStatisticsByUserUseCase($this->mockRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * デフォルトのlimit（5）で統計を取得できることをテストする
     * - リポジトリが正しく呼ばれることを確認
     */
    public function test_execute_calls_repository_with_default_limit()
    {
        // Arrange: テストデータの準備
        $dto = new GetTaskStatisticsByUserDto(limit: 5);

        $mockResult = collect([
            [
                'user' => (object)['id' => 1, 'name' => 'User 1', 'email' => 'user1@example.com'],
                'task_count' => 10,
                'recent_tasks' => collect(),
            ],
            [
                'user' => (object)['id' => 2, 'name' => 'User 2', 'email' => 'user2@example.com'],
                'task_count' => 5,
                'recent_tasks' => collect(),
            ],
        ]);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('getTaskCountByUser')
            ->with(5)
            ->once()
            ->andReturn($mockResult);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertEquals(10, $result[0]['task_count']);
        $this->assertEquals(5, $result[1]['task_count']);
    }

    /**
     * カスタムlimitで統計を取得できることをテストする
     * - limit=10が正しくリポジトリに渡されることを確認
     */
    public function test_execute_calls_repository_with_custom_limit()
    {
        // Arrange: テストデータの準備
        $dto = new GetTaskStatisticsByUserDto(limit: 10);

        $mockResult = collect([]);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('getTaskCountByUser')
            ->with(10)
            ->once()
            ->andReturn($mockResult);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertInstanceOf(Collection::class, $result);
    }

    /**
     * limit=1でトップユーザーのみ取得できることをテストする
     */
    public function test_execute_with_limit_one()
    {
        // Arrange: テストデータの準備
        $dto = new GetTaskStatisticsByUserDto(limit: 1);

        $mockResult = collect([
            [
                'user' => (object)['id' => 1, 'name' => 'Top User', 'email' => 'top@example.com'],
                'task_count' => 100,
                'recent_tasks' => collect(),
            ],
        ]);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('getTaskCountByUser')
            ->with(1)
            ->once()
            ->andReturn($mockResult);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertCount(1, $result);
        $this->assertEquals(100, $result[0]['task_count']);
        $this->assertEquals('Top User', $result[0]['user']->name);
    }

    /**
     * 空のコレクションが返されることをテストする
     * - タスクが存在しない場合
     */
    public function test_execute_returns_empty_collection_when_no_tasks()
    {
        // Arrange: テストデータの準備
        $dto = new GetTaskStatisticsByUserDto(limit: 5);

        $mockResult = collect([]);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('getTaskCountByUser')
            ->with(5)
            ->once()
            ->andReturn($mockResult);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 空のコレクションが返される
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEmpty($result);
    }

    /**
     * リポジトリから返されたコレクションがそのまま返されることをテストする
     * - UseCaseがリポジトリの戻り値を変更しないことを確認
     */
    public function test_execute_returns_repository_result_unchanged()
    {
        // Arrange: テストデータの準備
        $dto = new GetTaskStatisticsByUserDto(limit: 3);

        $expectedResult = collect([
            [
                'user' => (object)['id' => 1, 'name' => 'User A', 'email' => 'a@example.com'],
                'task_count' => 20,
                'recent_tasks' => collect([
                    (object)['id' => 1, 'title' => 'Task 1'],
                    (object)['id' => 2, 'title' => 'Task 2'],
                ]),
            ],
        ]);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('getTaskCountByUser')
            ->with(3)
            ->once()
            ->andReturn($expectedResult);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: リポジトリから返されたコレクションがそのまま返される
        $this->assertSame($expectedResult, $result);
    }

    /**
     * 大きなlimit値でも正しく動作することをテストする
     */
    public function test_execute_with_large_limit()
    {
        // Arrange: テストデータの準備
        $dto = new GetTaskStatisticsByUserDto(limit: 100);

        $mockResult = collect([]);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('getTaskCountByUser')
            ->with(100)
            ->once()
            ->andReturn($mockResult);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertInstanceOf(Collection::class, $result);
    }

    /**
     * DTOのlimit値がリポジトリに正しく渡されることをテストする
     */
    public function test_execute_passes_dto_limit_to_repository()
    {
        // Arrange: 様々なlimit値でテスト
        $testCases = [1, 5, 10, 20, 50];

        foreach ($testCases as $limitValue) {
            $dto = new GetTaskStatisticsByUserDto(limit: $limitValue);
            $mockResult = collect([]);

            // モックリポジトリの期待値を設定
            $this->mockRepository
                ->shouldReceive('getTaskCountByUser')
                ->with($limitValue)
                ->once()
                ->andReturn($mockResult);

            // Act: テスト対象メソッドの実行
            $result = $this->useCase->execute($dto);

            // Assert: 結果の検証
            $this->assertInstanceOf(Collection::class, $result);
        }
    }
}
