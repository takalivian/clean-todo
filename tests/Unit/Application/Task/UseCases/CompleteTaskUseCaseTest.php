<?php

namespace Tests\Unit\Application\Task\UseCases;

use App\Application\Task\DTOs\CompleteTaskDto;
use App\Application\Task\UseCases\CompleteTaskUseCase;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Models\Task;
use Mockery;
use PHPUnit\Framework\TestCase;

class CompleteTaskUseCaseTest extends TestCase
{
    private TaskRepositoryInterface $mockRepository;
    private CompleteTaskUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = Mockery::mock(TaskRepositoryInterface::class);
        $this->useCase = new CompleteTaskUseCase($this->mockRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * タスクを完了状態にできることをテストする
     * - ステータスがCOMPLETEDに設定される
     * - completed_atが現在時刻に設定される
     */
    public function test_execute_completes_task_successfully()
    {
        // Arrange: テストデータの準備
        $dto = new CompleteTaskDto(id: 1);

        $existingTask = Mockery::mock(Task::class);
        $existingTask->shouldReceive('trashed')
            ->andReturn(false);
        $existingTask->shouldReceive('getAttributes')
            ->andReturn(['status' => Task::STATUS_PENDING]);

        $mockCompletedTask = Mockery::mock(Task::class);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingTask);

        $this->mockRepository
            ->shouldReceive('update')
            ->with($existingTask, Mockery::on(function ($data) {
                // ステータスがCOMPLETEDに設定されることを確認
                $this->assertEquals(Task::STATUS_COMPLETED, $data['status']);
                // completed_atが設定されることを確認
                $this->assertNotNull($data['completed_at']);
                return true;
            }))
            ->once()
            ->andReturn($mockCompletedTask);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertSame($mockCompletedTask, $result);
    }

    /**
     * タスクが見つからない場合に例外が発生することをテストする
     */
    public function test_execute_throws_exception_when_task_not_found()
    {
        // Arrange: 存在しないタスクID
        $dto = new CompleteTaskDto(id: 999);

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
     * 削除済みタスクは完了できないことをテストする
     */
    public function test_execute_throws_exception_when_task_is_deleted()
    {
        // Arrange: 削除済みタスク
        $dto = new CompleteTaskDto(id: 1);

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
        $this->expectExceptionMessage('削除済みのタスクは完了できません。');

        // Act: テスト対象メソッドの実行
        $this->useCase->execute($dto);
    }

    /**
     * リポジトリから返されたタスクがそのまま返されることをテストする
     */
    public function test_execute_returns_updated_task_from_repository()
    {
        // Arrange: テストデータの準備
        $dto = new CompleteTaskDto(id: 1);

        $existingTask = Mockery::mock(Task::class);
        $existingTask->shouldReceive('trashed')
            ->andReturn(false);
        $existingTask->shouldReceive('getAttributes')
            ->andReturn(['status' => Task::STATUS_PENDING]);

        $mockCompletedTask = Mockery::mock(Task::class);

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingTask);

        $this->mockRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($mockCompletedTask);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: リポジトリから返されたタスクが返されることを確認
        $this->assertSame($mockCompletedTask, $result);
    }

    /**
     * 未着手のタスクを完了できることをテストする
     */
    public function test_execute_can_complete_pending_task()
    {
        // Arrange: 未着手タスク
        $dto = new CompleteTaskDto(id: 1);

        $pendingTask = Mockery::mock(Task::class);
        $pendingTask->shouldReceive('trashed')
            ->andReturn(false);
        $pendingTask->shouldReceive('getAttributes')
            ->andReturn(['status' => Task::STATUS_PENDING]);

        $mockCompletedTask = Mockery::mock(Task::class);

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($pendingTask);

        $this->mockRepository
            ->shouldReceive('update')
            ->with($pendingTask, Mockery::on(function ($data) {
                $this->assertEquals(Task::STATUS_COMPLETED, $data['status']);
                $this->assertNotNull($data['completed_at']);
                return true;
            }))
            ->once()
            ->andReturn($mockCompletedTask);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertSame($mockCompletedTask, $result);
    }

    /**
     * 進行中のタスクを完了できることをテストする
     */
    public function test_execute_can_complete_in_progress_task()
    {
        // Arrange: 進行中タスク
        $dto = new CompleteTaskDto(id: 1);

        $inProgressTask = Mockery::mock(Task::class);
        $inProgressTask->shouldReceive('trashed')
            ->andReturn(false);
        $inProgressTask->shouldReceive('getAttributes')
            ->andReturn(['status' => Task::STATUS_IN_PROGRESS]);

        $mockCompletedTask = Mockery::mock(Task::class);

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($inProgressTask);

        $this->mockRepository
            ->shouldReceive('update')
            ->with($inProgressTask, Mockery::on(function ($data) {
                $this->assertEquals(Task::STATUS_COMPLETED, $data['status']);
                $this->assertNotNull($data['completed_at']);
                return true;
            }))
            ->once()
            ->andReturn($mockCompletedTask);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertSame($mockCompletedTask, $result);
    }

    /**
     * 既に完了済みのタスクを完了しようとすると例外が発生することをテストする
     */
    public function test_execute_throws_exception_when_task_already_completed()
    {
        // Arrange: 完了済みタスク
        $dto = new CompleteTaskDto(id: 1);

        $completedTask = Mockery::mock(Task::class);
        $completedTask->shouldReceive('trashed')
            ->andReturn(false);
        $completedTask->shouldReceive('getAttributes')
            ->andReturn(['status' => Task::STATUS_COMPLETED]);

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($completedTask);

        // Assert: 例外が発生することを確認
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('既に完了済みのタスクです。');

        // Act: テスト対象メソッドの実行
        $this->useCase->execute($dto);
    }
}
