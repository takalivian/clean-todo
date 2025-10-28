<?php

namespace App\Application\Tag\UseCases;

use App\Application\Tag\DTOs\UpdateTagDto;
use App\Domain\Tag\Repositories\TagRepositoryInterface;
use App\Models\Tag;

class UpdateTagUseCase
{
    public function __construct(
        private readonly TagRepositoryInterface $tagRepository
    ) {
    }

    /**
     * タグを更新する
     *
     * @param UpdateTagDto $dto
     * @return Tag
     * @throws \Exception
     */
    public function execute(UpdateTagDto $dto): Tag
    {
        $tag = $this->tagRepository->findById($dto->id);

        if (!$tag) {
            throw new \Exception('タグが見つかりません');
        }

        if ($tag->trashed()) {
            throw new \Exception('削除済みのタグは編集できません');
        }

        $data = $dto->toArray();

        return $this->tagRepository->update($tag, $data);
    }
}
