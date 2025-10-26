<?php

namespace Tests\Unit\Application\Task\UseCases;

use App\Application\Task\DTOs\CreateTaskDto;
use App\Application\Task\UseCases\CreateTaskUseCase;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Models\Task;
use Mockery;
use PHPUnit\Framework\TestCase;

class CreateTaskUseCaseTest extends TestCase
{
    private TaskRepositoryInterface $mockRepository;
    private CreateTaskUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = Mockery::mock(TaskRepositoryInterface::class);
        $this->useCase = new CreateTaskUseCase($this->mockRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 通常のタスク作成をテストする
     * - ステータスがPENDINGの場合
     * - completed_atがnullに設定されることを確認
     * - リポジトリのcreateメソッドが正しいデータで呼ばれることを確認
     */
    public function test_execute_creates_task_with_pending_status()
    {
        // Arrange: テストデータの準備
        $dto = new CreateTaskDto(
            title: 'Test Task',
            description: 'Test Description',
            status: Task::STATUS_PENDING,
            due_date: '2025-12-31 23:59:59'
        );

        $expectedData = [
            'title' => 'Test Task',
            'description' => 'Test Description',
            'status' => Task::STATUS_PENDING,
            'due_date' => '2025-12-31 23:59:59',
            'completed_at' => null,
        ];

        $mockTask = Mockery::mock(Task::class);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('create')
            ->with(Mockery::on(function ($data) use ($expectedData) {
                // completed_atはnullであることを確認
                $this->assertNull($data['completed_at']);
                $this->assertEquals($expectedData['title'], $data['title']);
                $this->assertEquals($expectedData['description'], $data['description']);
                $this->assertEquals($expectedData['status'], $data['status']);
                $this->assertEquals($expectedData['due_date'], $data['due_date']);
                return true;
            }))
            ->once()
            ->andReturn($mockTask);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertSame($mockTask, $result);
    }

    /**
     * 完了ステータスでタスクを作成した場合のテスト
     * - ステータスがCOMPLETEDの場合
     * - completed_atが自動設定されることを確認
     * - リポジトリのcreateメソッドが正しいデータで呼ばれることを確認
     */
    public function test_execute_creates_task_with_completed_status_sets_completed_at()
    {
        // Arrange: 完了ステータスのテストデータを準備
        $dto = new CreateTaskDto(
            title: 'Completed Task',
            description: 'Already done',
            status: Task::STATUS_COMPLETED,
            due_date: '2025-12-31 23:59:59'
        );

        $mockTask = Mockery::mock(Task::class);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                // completed_atが設定されていることを確認
                $this->assertNotNull($data['completed_at']);
                $this->assertEquals(Task::STATUS_COMPLETED, $data['status']);
                return true;
            }))
            ->once()
            ->andReturn($mockTask);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertSame($mockTask, $result);
    }

    /**
     * 進行中ステータスでタスクを作成した場合のテスト
     * - ステータスがIN_PROGRESSの場合
     * - completed_atがnullに設定されることを確認
     */
    public function test_execute_creates_task_with_in_progress_status()
    {
        // Arrange: 進行中ステータスのテストデータを準備
        $dto = new CreateTaskDto(
            title: 'In Progress Task',
            description: 'Working on it',
            status: Task::STATUS_IN_PROGRESS,
            due_date: '2025-12-31 23:59:59'
        );

        $mockTask = Mockery::mock(Task::class);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                // completed_atがnullであることを確認
                $this->assertNull($data['completed_at']);
                $this->assertEquals(Task::STATUS_IN_PROGRESS, $data['status']);
                return true;
            }))
            ->once()
            ->andReturn($mockTask);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertSame($mockTask, $result);
    }

    /**
     * 最小限のフィールドでタスクを作成した場合のテスト
     * - titleのみ必須、その他はnull
     * - オプショナルフィールドが正しく処理されることを確認
     */
    public function test_execute_creates_task_with_minimal_fields()
    {
        // Arrange: 最小限のテストデータを準備
        $dto = new CreateTaskDto(
            title: 'Minimal Task',
            description: null,
            status: Task::STATUS_PENDING,
            due_date: null
        );

        $createdTask = new Task([
            'title' => 'Minimal Task',
            'description' => null,
            'status' => Task::STATUS_PENDING,
            'due_date' => null,
            'completed_at' => null,
        ]);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                $this->assertEquals('Minimal Task', $data['title']);
                $this->assertNull($data['description']);
                $this->assertNull($data['due_date']);
                $this->assertNull($data['completed_at']);
                return true;
            }))
            ->once()
            ->andReturn($createdTask);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertEquals($createdTask, $result);
    }

    /**
     * リポジトリから返されたTaskオブジェクトがそのまま返されることをテストする
     * - UseCaseがリポジトリの戻り値を変更しないことを確認
     */
    public function test_execute_returns_task_from_repository()
    {
        // Arrange: テストデータの準備
        $dto = new CreateTaskDto(
            title: 'Task',
            description: 'Description',
            status: Task::STATUS_PENDING,
            due_date: null
        );

        $expectedTask = new Task(['id' => 123, 'title' => 'Task']);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($expectedTask);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: リポジトリから返されたタスクがそのまま返されることを確認
        $this->assertSame($expectedTask, $result);
    }
}
