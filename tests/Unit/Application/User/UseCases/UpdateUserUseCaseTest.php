<?php

namespace Tests\Unit\Application\User\UseCases;

use App\Application\User\DTOs\UpdateUserDto;
use App\Application\User\UseCases\UpdateUserUseCase;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class UpdateUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface $mockRepository;
    private UpdateUserUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->useCase = new UpdateUserUseCase($this->mockRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 正常にユーザーを更新できる
     */
    public function test_execute_updates_user_successfully()
    {
        // Arrange
        $dto = UpdateUserDto::fromArray([
            'id' => 1,
            'name' => 'Updated Name',
        ]);

        $mockUser = Mockery::mock(User::class)->makePartial();
        $mockUser->email = 'test@example.com';

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($mockUser);

        $this->mockRepository
            ->shouldReceive('update')
            ->with($mockUser, ['name' => 'Updated Name'])
            ->once()
            ->andReturn($mockUser);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertSame($mockUser, $result);
    }

    /**
     * ユーザーが存在しない場合は例外をthrow
     */
    public function test_execute_throws_exception_when_user_not_found()
    {
        // Arrange
        $dto = UpdateUserDto::fromArray([
            'id' => 999,
            'name' => 'Updated Name',
        ]);

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
     * メールアドレスが重複している場合は例外をthrow
     */
    public function test_execute_throws_exception_when_email_already_exists()
    {
        // Arrange
        $dto = UpdateUserDto::fromArray([
            'id' => 1,
            'email' => 'existing@example.com',
        ]);

        $mockUser = Mockery::mock(User::class)->makePartial();
        $mockUser->email = 'old@example.com';

        $existingUser = Mockery::mock(User::class);

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($mockUser);

        $this->mockRepository
            ->shouldReceive('findByEmail')
            ->with('existing@example.com')
            ->once()
            ->andReturn($existingUser);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('このメールアドレスは既に使用されています');

        $this->useCase->execute($dto);
    }

    /**
     * パスワードが含まれている場合はハッシュ化される
     */
    public function test_execute_hashes_password_when_provided()
    {
        // Arrange
        Hash::shouldReceive('make')
            ->with('new_password')
            ->once()
            ->andReturn('hashed_new_password');

        $dto = UpdateUserDto::fromArray([
            'id' => 1,
            'password' => 'new_password',
        ]);

        $mockUser = Mockery::mock(User::class)->makePartial();
        $mockUser->email = 'test@example.com';

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($mockUser);

        $this->mockRepository
            ->shouldReceive('update')
            ->with($mockUser, Mockery::on(function ($data) {
                $this->assertEquals('hashed_new_password', $data['password']);
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
     * メールアドレスが変更されない場合は重複チェックをスキップ
     */
    public function test_execute_skips_duplicate_check_when_email_not_changed()
    {
        // Arrange
        $dto = UpdateUserDto::fromArray([
            'id' => 1,
            'email' => 'test@example.com',
            'name' => 'Updated Name',
        ]);

        $mockUser = Mockery::mock(User::class)->makePartial();
        $mockUser->email = 'test@example.com';

        $this->mockRepository
            ->shouldReceive('findById')
            ->with(1)
            ->once()
            ->andReturn($mockUser);

        // findByEmailは呼ばれないことを確認
        $this->mockRepository
            ->shouldNotReceive('findByEmail');

        $this->mockRepository
            ->shouldReceive('update')
            ->with($mockUser, Mockery::on(function ($data) {
                $this->assertEquals('test@example.com', $data['email']);
                $this->assertEquals('Updated Name', $data['name']);
                return true;
            }))
            ->once()
            ->andReturn($mockUser);

        // Act
        $result = $this->useCase->execute($dto);

        // Assert
        $this->assertSame($mockUser, $result);
    }
}
