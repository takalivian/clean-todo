<?php

namespace Tests\Unit\Application\Task\UseCases;

use App\Application\Task\DTOs\RestoreTaskDto;
use App\Application\Task\UseCases\RestoreTaskUseCase;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Models\Task;
use Mockery;
use PHPUnit\Framework\TestCase;

class RestoreTaskUseCaseTest extends TestCase
{
    private TaskRepositoryInterface $mockRepository;
    private RestoreTaskUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = Mockery::mock(TaskRepositoryInterface::class);
        $this->useCase = new RestoreTaskUseCase($this->mockRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 削除済みタスクを復元できることをテストする
     * - findDeletedByIdで削除済みタスクを取得
     * - リポジトリのrestoreメソッドが呼ばれることを確認
     */
    public function test_execute_restores_deleted_task_successfully()
    {
        // Arrange: テストデータの準備
        $dto = new RestoreTaskDto(id: 1);

        $deletedTask = new Task([
            'id' => 1,
            'title' => 'Deleted Task',
            'deleted_at' => now(),
        ]);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('findDeletedById')
            ->with(1)
            ->once()
            ->andReturn($deletedTask);

        $this->mockRepository
            ->shouldReceive('restore')
            ->with($deletedTask)
            ->once()
            ->andReturn(true);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertEquals($deletedTask, $result);
    }

    /**
     * 削除済みタスクが見つからない場合に例外が発生することをテストする
     */
    public function test_execute_throws_exception_when_deleted_task_not_found()
    {
        // Arrange: 存在しない削除済みタスクID
        $dto = new RestoreTaskDto(id: 999);

        $this->mockRepository
            ->shouldReceive('findDeletedById')
            ->with(999)
            ->once()
            ->andReturn(null);

        // Assert: 例外が発生することを確認
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('削除済みタスクが見つかりません。');

        // Act: テスト対象メソッドの実行
        $this->useCase->execute($dto);
    }

    /**
     * 復元後のタスクが返されることをテストする
     */
    public function test_execute_returns_restored_task()
    {
        // Arrange: テストデータの準備
        $dto = new RestoreTaskDto(id: 1);

        $deletedTask = new Task([
            'id' => 1,
            'title' => 'Task to Restore',
            'deleted_at' => now(),
        ]);

        $this->mockRepository
            ->shouldReceive('findDeletedById')
            ->with(1)
            ->once()
            ->andReturn($deletedTask);

        $this->mockRepository
            ->shouldReceive('restore')
            ->with($deletedTask)
            ->once()
            ->andReturn(true);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 復元されたタスクが返されることを確認
        $this->assertSame($deletedTask, $result);
    }

    /**
     * リポジトリメソッドが正しい順序で呼ばれることをテストする
     */
    public function test_execute_calls_repository_methods_in_correct_order()
    {
        // Arrange: テストデータの準備
        $dto = new RestoreTaskDto(id: 1);

        $deletedTask = new Task([
            'id' => 1,
            'title' => 'Task',
            'deleted_at' => now(),
        ]);

        // モックリポジトリの期待値を設定（順序も確認）
        $this->mockRepository
            ->shouldReceive('findDeletedById')
            ->with(1)
            ->once()
            ->ordered()
            ->andReturn($deletedTask);

        $this->mockRepository
            ->shouldReceive('restore')
            ->with($deletedTask)
            ->once()
            ->ordered()
            ->andReturn(true);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertEquals($deletedTask, $result);
    }

    /**
     * 異なるIDで複数のタスクを復元できることをテストする
     */
    public function test_execute_can_restore_multiple_different_tasks()
    {
        // Arrange: 複数のタスクを準備
        $dto1 = new RestoreTaskDto(id: 1);
        $dto2 = new RestoreTaskDto(id: 2);

        $deletedTask1 = new Task(['id' => 1, 'title' => 'Task 1']);
        $deletedTask2 = new Task(['id' => 2, 'title' => 'Task 2']);

        // Task 1の復元
        $this->mockRepository
            ->shouldReceive('findDeletedById')
            ->with(1)
            ->once()
            ->andReturn($deletedTask1);

        $this->mockRepository
            ->shouldReceive('restore')
            ->with($deletedTask1)
            ->once()
            ->andReturn(true);

        // Task 2の復元
        $this->mockRepository
            ->shouldReceive('findDeletedById')
            ->with(2)
            ->once()
            ->andReturn($deletedTask2);

        $this->mockRepository
            ->shouldReceive('restore')
            ->with($deletedTask2)
            ->once()
            ->andReturn(true);

        // Act: 複数のタスクを復元
        $result1 = $this->useCase->execute($dto1);
        $result2 = $this->useCase->execute($dto2);

        // Assert: それぞれのタスクが復元されることを確認
        $this->assertEquals($deletedTask1, $result1);
        $this->assertEquals($deletedTask2, $result2);
    }
}
