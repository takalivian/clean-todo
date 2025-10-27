<?php

namespace Tests\Unit\Application\User\UseCases;

use App\Application\User\DTOs\LoginUserDto;
use App\Application\User\UseCases\LoginUserUseCase;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;
use Mockery;
use Tests\TestCase;

class LoginUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface $mockRepository;
    private LoginUserUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->useCase = new LoginUserUseCase($this->mockRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 正常にログインできる
     */
    public function test_execute_logs_in_successfully()
    {
        // Arrange
        $dto = new LoginUserDto(
            email: 'test@example.com',
            password: 'password123'
        );

        $mockUser = Mockery::mock(User::class)->makePartial();
        $mockUser->password = 'hashed_password';

        $mockNewAccessToken = Mockery::mock(NewAccessToken::class);
        $mockNewAccessToken->plainTextToken = 'test_token_123';

        Hash::shouldReceive('check')
            ->with('password123', 'hashed_password')
            ->once()
            ->andReturn(true);

        $mockUser->shouldReceive('createToken')
            ->with('auth_token')
            ->once()
            ->andReturn($mockNewAccessToken);

        $this->mockRepository
            ->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn($mockUser);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertSame($mockUser, $result['user']);
        $this->assertEquals('test_token_123', $result['token']);
    }

    /**
     * ユーザーが存在しない場合は例外をthrow
     */
    public function test_execute_throws_exception_when_user_not_found()
    {
        // Arrange
        $dto = new LoginUserDto(
            email: 'nonexistent@example.com',
            password: 'password123'
        );

        $this->mockRepository
            ->shouldReceive('findByEmail')
            ->with('nonexistent@example.com')
            ->once()
            ->andReturn(null);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('メールアドレスまたはパスワードが正しくありません。');

        $this->useCase->execute($dto);
    }

    /**
     * パスワードが一致しない場合は例外をthrow
     */
    public function test_execute_throws_exception_when_password_incorrect()
    {
        // Arrange
        $dto = new LoginUserDto(
            email: 'test@example.com',
            password: 'wrong_password'
        );

        $mockUser = Mockery::mock(User::class)->makePartial();
        $mockUser->password = 'hashed_password';

        Hash::shouldReceive('check')
            ->with('wrong_password', 'hashed_password')
            ->once()
            ->andReturn(false);

        $this->mockRepository
            ->shouldReceive('findByEmail')
            ->with('test@example.com')
            ->once()
            ->andReturn($mockUser);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('メールアドレスまたはパスワードが正しくありません。');

        $this->useCase->execute($dto);
    }
}
