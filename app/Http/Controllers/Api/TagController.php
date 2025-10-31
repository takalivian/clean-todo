<?php

namespace App\Http\Controllers\Api;

use App\Application\Tag\DTOs\CreateTagDto;
use App\Application\Tag\DTOs\GetTagsDto;
use App\Application\Tag\DTOs\GetTagDto;
use App\Application\Tag\DTOs\UpdateTagDto;
use App\Application\Tag\DTOs\DeleteTagDto;
use App\Application\Tag\UseCases\CreateTagUseCase;
use App\Application\Tag\UseCases\GetTagsUseCase;
use App\Application\Tag\UseCases\GetTagUseCase;
use App\Application\Tag\UseCases\UpdateTagUseCase;
use App\Application\Tag\UseCases\DeleteTagUseCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\TagStoreRequest;
use App\Http\Requests\TagUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function __construct(
        private readonly CreateTagUseCase $createTagUseCase,
        private readonly GetTagsUseCase $getTagsUseCase,
        private readonly GetTagUseCase $getTagUseCase,
        private readonly UpdateTagUseCase $updateTagUseCase,
        private readonly DeleteTagUseCase $deleteTagUseCase,
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $dto = GetTagsDto::fromArray($request->all());

            $tags = $this->getTagsUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'data' => $tags
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
    public function store(TagStoreRequest $request): JsonResponse
    {
        try {
            $dto = CreateTagDto::fromArray([
                'user_id' => auth()->id(),
                'name' => $request->validated()['name'],
            ]);

            $tag = $this->createTagUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'message' => 'タグが作成されました',
                'data' => $tag
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
            $dto = GetTagDto::fromArray([
                'id' => $id,
            ]);

            $tag = $this->getTagUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'data' => $tag
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
    public function update(TagUpdateRequest $request, string $id): JsonResponse
    {
        try {
            $dto = UpdateTagDto::fromArray([
                'id' => $id,
                'updated_by' => auth()->id(),
                ...$request->validated(),
            ]);

            $tag = $this->updateTagUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'message' => 'タグが更新されました',
                'data' => $tag
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
            $dto = DeleteTagDto::fromArray(['id' => $id]);

            $this->deleteTagUseCase->execute($dto);

            return response()->json([
                'success' => true,
                'message' => 'タグが削除されました'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
