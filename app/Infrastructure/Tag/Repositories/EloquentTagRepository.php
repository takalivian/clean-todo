<?php

namespace App\Infrastructure\Tag\Repositories;

use App\Application\Tag\DTOs\GetTagsDto;
use App\Domain\Tag\Repositories\TagRepositoryInterface;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

class EloquentTagRepository implements TagRepositoryInterface
{
    /**
     * Eager Loadingで取得するリレーション
     */
    private const RELATIONS = [
        'user:id,name,email',
        'updater:id,name,email'
    ];

    /**
     * タグを作成する
     *
     * @param array $data
     * @return Tag
     */
    public function create(array $data): Tag
    {
        return Tag::create($data);
    }

    /**
     * IDでタグを取得する
     *
     * @param int $id
     * @return Tag|null
     */
    public function findById(int $id): ?Tag
    {
        return Tag::with(self::RELATIONS)->find($id);
    }

    /**
     * 全タグを取得する
     *
     * @return Collection
     */
    public function findAll(): Collection
    {
        return Tag::with(self::RELATIONS)->get();
    }

    /**
     * フィルタ条件付きでタグを取得する
     *
     * @param GetTagsDto $dto
     * @return Collection
     */
    public function findAllWithFilter(GetTagsDto $dto): Collection
    {
        $query = Tag::query();

        // Eager Loading: N+1問題を防ぐため、ユーザー情報を事前にロード
        $query->with(self::RELATIONS);

        // user_idでフィルタ
        if ($dto->userId) {
            $query->where('user_id', $dto->userId);
        }

        // キーワード検索（タグ名）
        if ($dto->keyword) {
            $query->where('name', 'like', '%' . $dto->keyword . '%');
        }

        // ソート
        $query->orderBy($dto->sortBy, $dto->sortDirection);

        return $query->get();
    }

    /**
     * タグを更新する
     *
     * @param Tag $tag
     * @param array $data
     * @return Tag
     */
    public function update(Tag $tag, array $data): Tag
    {
        $tag->update($data);
        return $tag->fresh(self::RELATIONS);
    }

    /**
     * タグを削除する（論理削除）
     *
     * @param Tag $tag
     * @return bool
     */
    public function delete(Tag $tag): bool
    {
        return $tag->delete();
    }
}
