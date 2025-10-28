<?php

namespace App\Application\Tag\UseCases;

use App\Application\Tag\DTOs\CreateTagDto;
use App\Domain\Tag\Repositories\TagRepositoryInterface;
use App\Models\Tag;

class CreateTagUseCase
{
    public function __construct(
        private readonly TagRepositoryInterface $tagRepository
    ) {
    }

    /**
     * タグを作成する
     *
     * @param CreateTagDto $dto
     * @return Tag
     */
    public function execute(CreateTagDto $dto): Tag
    {
        return $this->tagRepository->create($dto->toArray());
    }
}
