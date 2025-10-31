<?php

namespace App\Application\Tag\UseCases;

use App\Application\Tag\DTOs\GetTagDto;
use App\Domain\Tag\Repositories\TagRepositoryInterface;
use App\Models\Tag;

class GetTagUseCase
{
    public function __construct(
        private readonly TagRepositoryInterface $tagRepository
    ) {
    }

    /**
     * タグを取得する
     *
     * @param GetTagDto $dto
     * @return Tag
     * @throws \Exception
     */
    public function execute(GetTagDto $dto): Tag
    {
        $tag = $this->tagRepository->findById($dto->id);

        if (!$tag) {
            throw new \Exception('タグが見つかりませんでした');
        }

        return $tag;
    }
}
