<?php

namespace Tests\Unit\Application\User\UseCases;

use App\Application\User\DTOs\DeleteUserDto;
use App\Application\User\UseCases\DeleteUserUseCase;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;
use Mockery;
use PHPUnit\Framework\TestCase;

class DeleteUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface $mockRepository;
    private DeleteUserUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->useCase = new DeleteUserUseCase($this->mockRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 正常にユーザーを削除できる
     */
    public function test_execute_deletes_user_successfully()
    {
        // Arrange
        $dto = DeleteUserDto::fromArray(['id' => 1]);

        $mockUser = Mockery::mock(User::class);

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($mockUser);

        $this->mockRepository
            ->shouldReceive('delete')
            ->with($mockUser)
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * ユーザーが存在しない場合は例外をthrow
     */
    public function test_execute_throws_exception_when_user_not_found()
    {
        // Arrange
        $dto = DeleteUserDto::fromArray(['id' => 999]);

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(999)
            ->once()
            ->andReturn(null);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('ユーザーが見つかりません');

        $this->useCase->execute($dto);
    }

    /**
     * リポジトリのdeleteメソッドの戻り値がそのまま返される
     */
    public function test_execute_returns_repository_result()
    {
        // Arrange
        $dto = DeleteUserDto::fromArray(['id' => 1]);

        $mockUser = Mockery::mock(User::class);

        $this->mockRepository
            ->shouldReceive('findById')
            ->once()
            ->andReturn($mockUser);

        $this->mockRepository
            ->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertTrue($result);
    }
}
