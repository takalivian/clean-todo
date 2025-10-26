<?php

namespace Tests\Unit\Application\Task\UseCases;

use App\Application\Task\DTOs\DeleteTaskDto;
use App\Application\Task\UseCases\DeleteTaskUseCase;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Models\Task;
use Mockery;
use PHPUnit\Framework\TestCase;

class DeleteTaskUseCaseTest extends TestCase
{
    private TaskRepositoryInterface $mockRepository;
    private DeleteTaskUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = Mockery::mock(TaskRepositoryInterface::class);
        $this->useCase = new DeleteTaskUseCase($this->mockRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 通常のタスク削除が成功することをテストする
     * - アクティブなタスクを削除
     * - リポジトリのdeleteメソッドが正しく呼ばれることを確認
     */
    public function test_execute_deletes_task_successfully()
    {
        // Arrange: テストデータの準備
        $dto = new DeleteTaskDto(id: 1);

        $existingTask = Mockery::mock(Task::class);
        $existingTask->shouldReceive('trashed')
            ->andReturn(false);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingTask);

        $this->mockRepository
            ->shouldReceive('delete')
            ->with($existingTask)
            ->once()
            ->andReturn(true);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertTrue($result);
    }

    /**
     * タスクが見つからない場合に例外が発生することをテストする
     */
    public function test_execute_throws_exception_when_task_not_found()
    {
        // Arrange: 存在しないタスクID
        $dto = new DeleteTaskDto(id: 999);

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);

        // Assert: 例外が発生することを確認
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('タスクが見つかりません。');

        // Act: テスト対象メソッドの実行
        $this->useCase->execute($dto);
    }

    /**
     * 既に削除済みのタスクを削除しようとした場合に例外が発生することをテストする
     */
    public function test_execute_throws_exception_when_task_already_deleted()
    {
        // Arrange: 削除済みタスク
        $dto = new DeleteTaskDto(id: 1);

        $deletedTask = Mockery::mock(Task::class);
        $deletedTask->shouldReceive('trashed')
            ->andReturn(true);

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($deletedTask);

        // Assert: 例外が発生することを確認
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('既に削除されています。');

        // Act: テスト対象メソッドの実行
        $this->useCase->execute($dto);
    }

    /**
     * リポジトリの戻り値がそのまま返されることをテストする
     */
    public function test_execute_returns_repository_result()
    {
        // Arrange: テストデータの準備
        $dto = new DeleteTaskDto(id: 1);

        $existingTask = Mockery::mock(Task::class);
        $existingTask->shouldReceive('trashed')
            ->andReturn(false);

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingTask);

        $this->mockRepository
            ->shouldReceive('delete')
            ->with($existingTask)
            ->once()
            ->andReturn(true);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: リポジトリの戻り値が返されることを確認
        $this->assertTrue($result);
    }
}
