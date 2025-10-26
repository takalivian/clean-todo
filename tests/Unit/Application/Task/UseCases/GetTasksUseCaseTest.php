<?php

namespace Tests\Unit\Application\Task\UseCases;

use App\Application\Task\DTOs\GetTasksDto;
use App\Application\Task\UseCases\GetTasksUseCase;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
     * フィルタリング付きでタスク一覧を取得できる
     */
    public function test_execute_returns_filtered_tasks()
    {
        // Arrange
        $dto = GetTasksDto::fromArray([
            'status' => 0,
            'page' => 1,
            'per_page' => 15,
        ]);

        $mockPaginator = Mockery::mock(LengthAwarePaginator::class);

        $this->mockRepository
            ->shouldReceive('findAllWithFilter')
            ->with($dto)
            ->once()
            ->andReturn($mockPaginator);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertSame($mockPaginator, $result);
    }

    /**
     * ページネーション付きでタスク一覧を取得できる
     */
    public function test_execute_returns_paginated_tasks()
    {
        // Arrange
        $dto = GetTasksDto::fromArray([
            'page' => 2,
            'per_page' => 10,
        ]);

        $mockPaginator = Mockery::mock(LengthAwarePaginator::class);

        $this->mockRepository
            ->shouldReceive('findAllWithFilter')
            ->with($dto)
            ->once()
            ->andReturn($mockPaginator);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    /**
     * キーワード検索付きでタスク一覧を取得できる
     */
    public function test_execute_returns_tasks_with_keyword_search()
    {
        // Arrange
        $dto = GetTasksDto::fromArray([
            'keyword' => 'test',
        ]);

        $mockPaginator = Mockery::mock(LengthAwarePaginator::class);

        $this->mockRepository
            ->shouldReceive('findAllWithFilter')
            ->with($dto)
            ->once()
            ->andReturn($mockPaginator);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertSame($mockPaginator, $result);
    }

    /**
     * リポジトリのfindAllWithFilterメソッドが呼ばれる
     */
    public function test_execute_calls_repository_find_all_with_filter()
    {
        // Arrange
        $dto = GetTasksDto::fromArray([]);

        $mockPaginator = Mockery::mock(LengthAwarePaginator::class);

        $this->mockRepository
            ->shouldReceive('findAllWithFilter')
            ->with(Mockery::type(GetTasksDto::class))
            ->once()
            ->andReturn($mockPaginator);

        // Act
        $this->useCase->execute($dto);

        // Assert: mockRepository の shouldReceive で検証済み
        $this->assertTrue(true);
    }
}
