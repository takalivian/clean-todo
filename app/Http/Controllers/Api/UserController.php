<?php

namespace App\Http\Controllers\Api;

use App\Application\User\DTOs\DeleteUserDto;
use App\Application\User\DTOs\GetUsersDto;
use App\Application\User\DTOs\UpdateUserDto;
use App\Application\User\UseCases\DeleteUserUseCase;
use App\Application\User\UseCases\GetUsersUseCase;
use App\Application\User\UseCases\UpdateUserUseCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserUpdateRequest;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(
        private readonly GetUsersUseCase $getUsersUseCase,
        private readonly UpdateUserUseCase $updateUserUseCase,
        private readonly DeleteUserUseCase $deleteUserUseCase,
    ) {
    }

    /**
     * ユーザー一覧を取得
     */
    public function index(): JsonResponse
    {
        try {
            $dto = GetUsersDto::fromArray([]);
            $users = $this->getUsersUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * ユーザーを更新
     */
    public function update(UserUpdateRequest $request, string $id): JsonResponse
    {
        try {
            $dto = UpdateUserDto::fromArray([
                'id' => $id,
                ...$request->validated(),
            ]);

            $user = $this->updateUserUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'message' => 'ユーザー情報が更新されました',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * ユーザーを削除（ソフトデリート）
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $dto = DeleteUserDto::fromArray(['id' => $id]);

            $this->deleteUserUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'message' => 'ユーザーが削除されました'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
