<?php

namespace App\Domain\Tag\Repositories;

use App\Application\Tag\DTOs\GetTagsDto;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

interface TagRepositoryInterface
{
    /**
     * タグを作成する
     *
     * @param array $data
     * @return Tag
     */
    public function create(array $data): Tag;

    /**
     * IDでタグを取得する
     *
     * @param int $id
     * @return Tag|null
     */
    public function findById(int $id): ?Tag;

    /**
     * 全タグを取得する
     *
     * @return Collection
     */
    public function findAll(): Collection;

    /**
     * フィルタ条件付きでタグを取得する
     *
     * @param GetTagsDto $dto
     * @return Collection
     */
    public function findAllWithFilter(GetTagsDto $dto): Collection;

    /**
     * タグを更新する
     *
     * @param Tag $tag
     * @param array $data
     * @return Tag
     */
    public function update(Tag $tag, array $data): Tag;

    /**
     * タグを削除する（論理削除）
     *
     * @param Tag $tag
     * @return bool
     */
    public function delete(Tag $tag): bool;
}
