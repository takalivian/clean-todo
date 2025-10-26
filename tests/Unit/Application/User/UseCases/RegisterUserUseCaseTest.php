<?php

namespace Tests\Unit\Application\User\UseCases;

use App\Application\User\DTOs\RegisterUserDto;
use App\Application\User\UseCases\RegisterUserUseCase;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class RegisterUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface $mockRepository;
    private RegisterUserUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->useCase = new RegisterUserUseCase($this->mockRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 正常にユーザーを登録できる
     */
    public function test_execute_registers_user_successfully()
    {
        // Arrange
        Hash::shouldReceive('make')
            ->once()
            ->with('password123')
            ->andReturn('hashed_password');

        $dto = new RegisterUserDto(
            name: 'Test User',
            email: 'test@example.com',
            password: 'password123'
        );

        $mockUser = Mockery::mock(User::class);

        $this->mockRepository
            ->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn(null);

        $this->mockRepository
            ->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                $this->assertEquals('Test User', $data['name']);
                $this->assertEquals('test@example.com', $data['email']);
                $this->assertEquals('hashed_password', $data['password']);
                return true;
            }))
            ->once()
            ->andReturn($mockUser);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertSame($mockUser, $result);
    }

    /**
     * メールアドレスが重複している場合は例外をthrow
     */
    public function test_execute_throws_exception_when_email_already_exists()
    {
        // Arrange
        $dto = new RegisterUserDto(
            name: 'Test User',
            email: 'existing@example.com',
            password: 'password123'
        );

        $existingUser = Mockery::mock(User::class);

        $this->mockRepository
            ->shouldReceive('findByEmail')
            ->with('existing@example.com')
            ->once()
            ->andReturn($existingUser);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('このメールアドレスは既に登録されています');

        $this->useCase->execute($dto);
    }

    /**
     * リポジトリのcreateメソッドが正しく呼ばれる
     */
    public function test_execute_calls_repository_create_method()
    {
        // Arrange
        Hash::shouldReceive('make')
            ->once()
            ->andReturn('hashed_password');

        $dto = new RegisterUserDto(
            name: 'New User',
            email: 'new@example.com',
            password: 'password123'
        );

        $mockUser = Mockery::mock(User::class);

        $this->mockRepository
            ->shouldReceive('findByEmail')
            ->once()
            ->andReturn(null);

        $this->mockRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($mockUser);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertSame($mockUser, $result);
    }
}
