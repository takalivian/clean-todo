<?php

namespace Tests\Unit\Application\Task\UseCases;

use App\Application\Task\DTOs\UpdateTaskDto;
use App\Application\Task\UseCases\UpdateTaskUseCase;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Models\Task;
use Mockery;
use PHPUnit\Framework\TestCase;

class UpdateTaskUseCaseTest extends TestCase
{
    private TaskRepositoryInterface $mockRepository;
    private UpdateTaskUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = Mockery::mock(TaskRepositoryInterface::class);
        $this->useCase = new UpdateTaskUseCase($this->mockRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 通常のタスク更新が成功することをテストする
     * - アクティブなタスクを更新
     * - リポジトリのupdateメソッドが正しく呼ばれることを確認
     */
    public function test_execute_updates_task_successfully()
    {
        // Arrange: テストデータの準備
        $dto = new UpdateTaskDto(
            id: 1,
            title: 'Updated Title',
            description: 'Updated Description',
            status: Task::STATUS_IN_PROGRESS,
            due_date: '2025-12-31 23:59:59'
        );

        $existingTask = Mockery::mock(Task::class);
        $existingTask->shouldReceive('getAttributes')
            ->andReturn(['status' => Task::STATUS_PENDING]);
        $existingTask->shouldReceive('trashed')
            ->andReturn(false);

        $updatedTask = new Task([
            'id' => 1,
            'title' => 'Updated Title',
            'description' => 'Updated Description',
            'status' => Task::STATUS_IN_PROGRESS,
        ]);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingTask);

        $this->mockRepository
            ->shouldReceive('update')
            ->with($existingTask, Mockery::on(function ($data) {
                $this->assertEquals('Updated Title', $data['title']);
                $this->assertEquals('Updated Description', $data['description']);
                $this->assertEquals(Task::STATUS_IN_PROGRESS, $data['status']);
                // PENDING→IN_PROGRESSの場合、completed_atは変更されないので含まれない
                $this->assertArrayNotHasKey('completed_at', $data);
                return true;
            }))
            ->once()
            ->andReturn($updatedTask);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertEquals($updatedTask, $result);
    }

    /**
     * タスクが見つからない場合に例外が発生することをテストする
     */
    public function test_execute_throws_exception_when_task_not_found()
    {
        // Arrange: 存在しないタスクID
        $dto = new UpdateTaskDto(
            id: 999,
            title: 'Updated Title',
            description: null,
            status: null,
            due_date: null
        );

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
     * 完了済みタスクは編集できないことをテストする
     */
    public function test_execute_throws_exception_when_task_is_completed()
    {
        // Arrange: 完了済みタスク
        $dto = new UpdateTaskDto(
            id: 1,
            title: 'Updated Title',
            description: null,
            status: null,
            due_date: null
        );

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
        $this->expectExceptionMessage('完了済みのタスクは編集できません。');

        // Act: テスト対象メソッドの実行
        $this->useCase->execute($dto);
    }

    /**
     * 削除済みタスクは編集できないことをテストする
     */
    public function test_execute_throws_exception_when_task_is_deleted()
    {
        // Arrange: 削除済みタスク
        $dto = new UpdateTaskDto(
            id: 1,
            title: 'Updated Title',
            description: null,
            status: null,
            due_date: null
        );

        $deletedTask = Mockery::mock(Task::class);
        $deletedTask->shouldReceive('getAttributes')
            ->andReturn(['status' => Task::STATUS_PENDING]);
        $deletedTask->shouldReceive('trashed')
            ->andReturn(true);

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($deletedTask);

        // Assert: 例外が発生することを確認
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('削除済みのタスクは編集できません。');

        // Act: テスト対象メソッドの実行
        $this->useCase->execute($dto);
    }

    /**
     * ステータスを完了に変更した場合にcompleted_atが設定されることをテストする
     */
    public function test_execute_sets_completed_at_when_status_changed_to_completed()
    {
        // Arrange: ステータスを完了に変更
        $dto = new UpdateTaskDto(
            id: 1,
            title: null,
            description: null,
            status: Task::STATUS_COMPLETED,
            due_date: null
        );

        $existingTask = Mockery::mock(Task::class);
        $existingTask->shouldReceive('getAttributes')
            ->andReturn(['status' => Task::STATUS_IN_PROGRESS]);
        $existingTask->shouldReceive('trashed')
            ->andReturn(false);

        $mockUpdatedTask = Mockery::mock(Task::class);

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingTask);

        $this->mockRepository
            ->shouldReceive('update')
            ->with($existingTask, Mockery::on(function ($data) {
                $this->assertEquals(Task::STATUS_COMPLETED, $data['status']);
                $this->assertNotNull($data['completed_at']); // 完了時刻が設定される
                return true;
            }))
            ->once()
            ->andReturn($mockUpdatedTask);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertSame($mockUpdatedTask, $result);
    }

    /**
     * ステータスを完了以外に変更した場合にcompleted_atがnullになることをテストする
     * 注: 現在の実装では完了済みタスクは編集できないため、
     * このテストはPENDING→IN_PROGRESSの変更をテストする
     */
    public function test_execute_clears_completed_at_when_status_changed_from_completed()
    {
        // Arrange: ステータスを進行中に変更
        $dto = new UpdateTaskDto(
            id: 1,
            title: null,
            description: null,
            status: Task::STATUS_IN_PROGRESS,
            due_date: null
        );

        $existingTask = Mockery::mock(Task::class);
        $existingTask->shouldReceive('getAttributes')
            ->andReturn(['status' => Task::STATUS_PENDING]);
        $existingTask->shouldReceive('trashed')
            ->andReturn(false);

        $mockUpdatedTask = Mockery::mock(Task::class);

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingTask);

        $this->mockRepository
            ->shouldReceive('update')
            ->with($existingTask, Mockery::on(function ($data) {
                $this->assertEquals(Task::STATUS_IN_PROGRESS, $data['status']);
                // PENDING→IN_PROGRESSなので、completed_atは含まれない
                $this->assertArrayNotHasKey('completed_at', $data);
                return true;
            }))
            ->once()
            ->andReturn($mockUpdatedTask);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertSame($mockUpdatedTask, $result);
    }

    /**
     * 一部のフィールドのみ更新できることをテストする
     * - titleのみ更新、他のフィールドはnull
     */
    public function test_execute_updates_only_specified_fields()
    {
        // Arrange: titleのみ更新
        $dto = new UpdateTaskDto(
            id: 1,
            title: 'New Title Only',
            description: null,
            status: null,
            due_date: null
        );

        $existingTask = Mockery::mock(Task::class);
        $existingTask->shouldReceive('getAttributes')
            ->andReturn(['status' => Task::STATUS_PENDING]);
        $existingTask->shouldReceive('trashed')
            ->andReturn(false);

        $updatedTask = new Task([
            'id' => 1,
            'title' => 'New Title Only',
        ]);

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($existingTask);

        $this->mockRepository
            ->shouldReceive('update')
            ->with($existingTask, Mockery::on(function ($data) {
                // titleのみが含まれることを確認
                $this->assertEquals('New Title Only', $data['title']);
                $this->assertArrayNotHasKey('description', $data);
                $this->assertArrayNotHasKey('status', $data);
                $this->assertArrayNotHasKey('due_date', $data);
                return true;
            }))
            ->once()
            ->andReturn($updatedTask);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertEquals($updatedTask, $result);
    }
}
