<?php

namespace App\Application\Tag\UseCases;

use App\Application\Tag\DTOs\DeleteTagDto;
use App\Domain\Tag\Repositories\TagRepositoryInterface;

class DeleteTagUseCase
{
    public function __construct(
        private readonly TagRepositoryInterface $tagRepository
    ) {
    }

    /**
     * タグを削除する（論理削除）
     *
     * @param DeleteTagDto $dto
     * @return bool
     * @throws \Exception
     */
    public function execute(DeleteTagDto $dto): bool
    {
        $tag = $this->tagRepository->findById($dto->id);

        if (!$tag) {
            throw new \Exception('タグが見つかりません');
        }

        if ($tag->trashed()) {
            throw new \Exception('既に削除されています');
        }

        return $this->tagRepository->delete($tag);
    }
}
