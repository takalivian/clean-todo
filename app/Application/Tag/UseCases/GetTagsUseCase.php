<?php

namespace App\Application\Tag\UseCases;

use App\Application\Tag\DTOs\GetTagsDto;
use App\Domain\Tag\Repositories\TagRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class GetTagsUseCase
{
    public function __construct(
        private readonly TagRepositoryInterface $tagRepository
    ) {
    }

    /**
     * タグ一覧を取得する
     *
     * @param GetTagsDto $dto
     * @return Collection
     */
    public function execute(GetTagsDto $dto): Collection
    {
        return $this->tagRepository->findAllWithFilter($dto);
    }
}
