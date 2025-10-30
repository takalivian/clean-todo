<?php

namespace App\Http\Controllers\Api;

use App\Application\Task\DTOs\AttachTagsToTaskDto;
use App\Application\Task\DTOs\CompleteTaskDto;
use App\Application\Task\DTOs\CreateTaskDto;
use App\Application\Task\DTOs\DeleteTaskDto;
use App\Application\Task\DTOs\DetachTagsFromTaskDto;
use App\Application\Task\DTOs\GetTaskDto;
use App\Application\Task\DTOs\GetTasksDto;
use App\Application\Task\DTOs\GetTaskStatisticsByUserDto;
use App\Application\Task\DTOs\RestoreTaskDto;
use App\Application\Task\DTOs\UpdateTaskDto;
use App\Application\Task\UseCases\AttachTagsToTaskUseCase;
use App\Application\Task\UseCases\CompleteTaskUseCase;
use App\Application\Task\UseCases\CreateTaskUseCase;
use App\Application\Task\UseCases\DeleteTaskUseCase;
use App\Application\Task\UseCases\DetachTagsFromTaskUseCase;
use App\Application\Task\UseCases\GetTasksUseCase;
use App\Application\Task\UseCases\GetTaskStatisticsByUserUseCase;
use App\Application\Task\UseCases\GetTaskUseCase;
use App\Application\Task\UseCases\RestoreTaskUseCase;
use App\Application\Task\UseCases\UpdateTaskUseCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\TaskStoreRequest;
use App\Http\Requests\TaskUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        private readonly CreateTaskUseCase $createTaskUseCase,
        private readonly GetTasksUseCase $getTasksUseCase,
        private readonly GetTaskUseCase $getTaskUseCase,
        private readonly UpdateTaskUseCase $updateTaskUseCase,
        private readonly DeleteTaskUseCase $deleteTaskUseCase,
        private readonly CompleteTaskUseCase $completeTaskUseCase,
        private readonly RestoreTaskUseCase $restoreTaskUseCase,
        private readonly AttachTagsToTaskUseCase $attachTagsToTaskUseCase,
        private readonly DetachTagsFromTaskUseCase $detachTagsFromTaskUseCase,
        private readonly GetTaskStatisticsByUserUseCase $getTaskStatisticsByUserUseCase,
    ) {
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $dto = GetTasksDto::fromArray($request->all());

            $paginator = $this->getTasksUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'data' => $paginator->items(),
                'pagination' => [
                    'total' => $paginator->total(),
                    'count' => $paginator->count(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TaskStoreRequest $request): JsonResponse
    {
        try {
            $dto = CreateTaskDto::fromArray([
                'user_id' => auth()->id(),
                ...$request->validated(),
            ]);

            $task = $this->createTaskUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'message' => 'タスクが作成されました',
                'data' => $task
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $dto = GetTaskDto::fromArray([
                'id' => $id,
            ]);

            $task = $this->getTaskUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'data' => $task
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TaskUpdateRequest $request, string $id): JsonResponse
    {
        try {
            $dto = UpdateTaskDto::fromArray([
                'id' => $id,
                'updated_by' => auth()->id(),
                ...$request->validated(),
            ]);

            $task = $this->updateTaskUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'message' => 'タスクが更新されました',
                'data' => $task
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $dto = DeleteTaskDto::fromArray(['id' => $id]);

            $this->deleteTaskUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'message' => 'タスクが削除されました'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * タスクを完了状態にする
     */
    public function complete(string $id): JsonResponse
    {
        try {
            $dto = CompleteTaskDto::fromArray([
                'id' => $id,
                'updated_by' => auth()->id(),
            ]);

            $task = $this->completeTaskUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'message' => 'タスクが完了しました',
                'data' => $task
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * 削除されたタスクを復元する
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $dto = RestoreTaskDto::fromArray(['id' => $id]);

            $task = $this->restoreTaskUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'message' => 'タスクが復元されました',
                'data' => $task
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * タスクにタグを付与する
     */
    public function attachTags(Request $request, string $id): JsonResponse
    {
        try {
            $dto = AttachTagsToTaskDto::fromArray([
                'task_id' => $id,
                'tag_ids' => $request->input('tag_ids', []),
            ]);

            $task = $this->attachTagsToTaskUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'message' => 'タグを付与しました',
                'data' => $task
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * タスクからタグを削除する
     */
    public function detachTags(Request $request, string $id): JsonResponse
    {
        try {
            $dto = DetachTagsFromTaskDto::fromArray([
                'task_id' => $id,
                'tag_ids' => $request->input('tag_ids', []),
            ]);

            $task = $this->detachTagsFromTaskUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'message' => 'タグを削除しました',
                'data' => $task
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * ユーザー別のタスク作成数統計を取得する
     */
    public function statisticsByUser(Request $request): JsonResponse
    {
        try {
            $dto = GetTaskStatisticsByUserDto::fromArray([
                'limit' => $request->input('limit', 5),
            ]);

            $startTime = microtime(true);
            $statistics = $this->getTaskStatisticsByUserUseCase->execute($dto);
            $executionTime = microtime(true) - $startTime;
            // ログに出力
            \Log::info("Task retrieval took: " . $executionTime . " seconds");
            

            return response()->json([
                'success' => true,
                'message' => 'ユーザー別タスク統計を取得しました',
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
