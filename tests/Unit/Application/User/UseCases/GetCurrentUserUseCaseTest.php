<?php

namespace Tests\Unit\Application\User\UseCases;

use App\Application\User\UseCases\GetCurrentUserUseCase;
use App\Models\User;
use Mockery;
use PHPUnit\Framework\TestCase;

class GetCurrentUserUseCaseTest extends TestCase
{
    private GetCurrentUserUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useCase = new GetCurrentUserUseCase();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 渡されたユーザーがそのまま返される
     */
    public function test_execute_returns_user()
    {
        // Arrange
        $mockUser = Mockery::mock(User::class);

        // Act
        $result = $this->useCase->execute($mockUser);

        // Assert
        $this->assertSame($mockUser, $result);
    }
}
