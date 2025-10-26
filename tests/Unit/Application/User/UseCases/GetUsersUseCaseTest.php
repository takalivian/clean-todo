<?php

namespace Tests\Unit\Application\User\UseCases;

use App\Application\User\DTOs\GetUsersDto;
use App\Application\User\UseCases\GetUsersUseCase;
use App\Domain\User\Repositories\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use PHPUnit\Framework\TestCase;

class GetUsersUseCaseTest extends TestCase
{
    private UserRepositoryInterface $mockRepository;
    private GetUsersUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->useCase = new GetUsersUseCase($this->mockRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * ユーザー一覧を取得できる
     */
    public function test_execute_returns_all_users()
    {
        // Arrange
        $dto = GetUsersDto::fromArray([]);

        $mockCollection = Mockery::mock(Collection::class);

        $this->mockRepository
            ->shouldReceive('findAll')
            ->once()
            ->andReturn($mockCollection);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertSame($mockCollection, $result);
    }

    /**
     * リポジトリのfindAllメソッドが呼ばれる
     */
    public function test_execute_calls_repository_find_all()
    {
        // Arrange
        $dto = GetUsersDto::fromArray([]);

        $mockCollection = Mockery::mock(Collection::class);

        $this->mockRepository
            ->shouldReceive('findAll')
            ->once()
            ->andReturn($mockCollection);

        // Act
        $this->useCase->execute($dto);

        // Assert: mockRepository の shouldReceive で検証済み
        $this->assertTrue(true);
    }
}
