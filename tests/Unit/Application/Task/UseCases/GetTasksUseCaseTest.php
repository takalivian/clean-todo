<?php

namespace Tests\Unit\Application\Task\UseCases;

use App\Application\Task\DTOs\GetTasksDto;
use App\Application\Task\UseCases\GetTasksUseCase;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use PHPUnit\Framework\TestCase;

class GetTasksUseCaseTest extends TestCase
{
    private TaskRepositoryInterface $mockRepository;
    private GetTasksUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockRepository = Mockery::mock(TaskRepositoryInterface::class);
        $this->useCase = new GetTasksUseCase($this->mockRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 通常のタスク一覧取得（削除されていないタスクのみ）をテストする
     * - onlyDeleted: false, withDeleted: false でリポジトリを呼び出す
     * - 期待されるタスクのコレクションが返されることを確認
     */
    public function test_execute_returns_all_tasks()
    {
        // Arrange: テストデータの準備
        $dto = new GetTasksDto(onlyDeleted: false, withDeleted: false);
        $expectedTasks = new Collection([
            new Task(['id' => 1, 'title' => 'Task 1']),
            new Task(['id' => 2, 'title' => 'Task 2'])
        ]);

        // モックリポジトリの期待値を設定
        $this->mockRepository
            ->shouldReceive('findAll')
            ->with(false, false)  // 通常のタスク取得パラメータ
            ->once()
            ->andReturn($expectedTasks);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 結果の検証
        $this->assertEquals($expectedTasks, $result);
    }

    /**
     * 削除済みタスクのみの取得をテストする
     * - onlyDeleted: true, withDeleted: false でリポジトリを呼び出す
     * - 削除済みタスクのコレクションが返されることを確認
     */
    public function test_execute_returns_only_deleted_tasks()
    {
        // Arrange: 削除済みタスク取得のテストデータを準備
        $dto = new GetTasksDto(onlyDeleted: true, withDeleted: false);
        $expectedTasks = new Collection([
            new Task(['id' => 3, 'title' => 'Deleted Task'])
        ]);

        // モックリポジトリの期待値を設定（削除済みのみ取得）
        $this->mockRepository
            ->shouldReceive('findAll')
            ->with(true, false)  // 削除済みのみ取得パラメータ
            ->once()
            ->andReturn($expectedTasks);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 削除済みタスクが返されることを確認
        $this->assertEquals($expectedTasks, $result);
    }

    /**
     * 全タスク取得（削除済み含む）をテストする
     * - onlyDeleted: false, withDeleted: true でリポジトリを呼び出す
     * - アクティブと削除済みの両方のタスクが返されることを確認
     */
    public function test_execute_returns_all_tasks_including_deleted()
    {
        // Arrange: 全タスク取得のテストデータを準備
        $dto = new GetTasksDto(onlyDeleted: false, withDeleted: true);
        $expectedTasks = new Collection([
            new Task(['id' => 1, 'title' => 'Active Task']),
            new Task(['id' => 2, 'title' => 'Deleted Task'])
        ]);

        // モックリポジトリの期待値を設定（削除済み含む全取得）
        $this->mockRepository
            ->shouldReceive('findAll')
            ->with(false, true)  // 削除済み含む全取得パラメータ
            ->once()
            ->andReturn($expectedTasks);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: アクティブと削除済みの両方が返されることを確認
        $this->assertEquals($expectedTasks, $result);
    }

    /**
     * タスクが存在しない場合の空コレクション返却をテストする
     * - リポジトリが空のコレクションを返す場合の動作を確認
     * - 空のコレクションが返され、型が正しいことを確認
     */
    public function test_execute_returns_empty_collection_when_no_tasks()
    {
        // Arrange: 空の結果を返すテストデータを準備
        $dto = new GetTasksDto(onlyDeleted: false, withDeleted: false);
        $expectedTasks = new Collection([]);

        // モックリポジトリの期待値を設定（空のコレクションを返す）
        $this->mockRepository
            ->shouldReceive('findAll')
            ->with(false, false)  // 通常のタスク取得パラメータ
            ->once()
            ->andReturn($expectedTasks);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 空のコレクションが返されることを確認
        $this->assertEmpty($result);
        $this->assertInstanceOf(Collection::class, $result);
    }

    /**
     * パラメータが競合する場合の動作をテストする
     * - onlyDeleted: true, withDeleted: true の場合
     * - onlyDeletedが優先され、削除済みのみ取得されることを確認
     */
    public function test_execute_prioritizes_only_deleted_when_both_flags_true()
    {
        // Arrange: 競合するパラメータのテストデータを準備
        $dto = new GetTasksDto(onlyDeleted: true, withDeleted: true);
        $expectedTasks = new Collection([
            new Task(['id' => 3, 'title' => 'Deleted Task', 'deleted_at' => now()])
        ]);

        // モックリポジトリの期待値を設定（onlyDeletedが優先される）
        $this->mockRepository
            ->shouldReceive('findAll')
            ->with(true, true)  // 両方がtrueでもonlyDeletedが優先
            ->once()
            ->andReturn($expectedTasks);

        // Act: テスト対象メソッドの実行
        $result = $this->useCase->execute($dto);

        // Assert: 削除済みのみが返されることを確認
        $this->assertEquals($expectedTasks, $result);
    }
}
