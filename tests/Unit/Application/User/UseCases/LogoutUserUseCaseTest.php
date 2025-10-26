<?php

namespace Tests\Unit\Application\User\UseCases;

use App\Application\User\UseCases\LogoutUserUseCase;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use PHPUnit\Framework\TestCase;

class LogoutUserUseCaseTest extends TestCase
{
    private LogoutUserUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useCase = new LogoutUserUseCase();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 現在のトークンが削除される
     */
    public function test_execute_deletes_current_token()
    {
        // Arrange
        $mockToken = Mockery::mock(PersonalAccessToken::class);
        $mockToken->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $mockUser = Mockery::mock(User::class);
        $mockUser->shouldReceive('currentAccessToken')
            ->once()
            ->andReturn($mockToken);

        // Act
        $this->useCase->execute($mockUser);

        // Assert: mockのshouldReceiveで検証済み
        $this->assertTrue(true);
    }
}
