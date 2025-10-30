<?php

namespace App\Application\Task\UseCases;

use App\Application\Task\DTOs\GetTaskStatisticsByUserDto;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class GetTaskStatisticsByUserUseCase
{
    // キャッシュのTTL（秒）: 10分
    private const CACHE_TTL = 600;

    // キャッシュキーのプレフィックス
    private const CACHE_KEY_PREFIX = 'task_statistics_by_user';

    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {}

    /**
     * ユーザー別のタスク作成数統計を取得する
     * キャッシュを使用して高速化
     *
     * @param GetTaskStatisticsByUserDto $dto
     * @return Collection
     */
    public function execute(GetTaskStatisticsByUserDto $dto): Collection
    {
        // キャッシュキーを生成（limitごとに異なるキャッシュ）
        $cacheKey = $this->getCacheKey($dto->limit);

        // キャッシュから取得、存在しない場合はDBから取得してキャッシュに保存
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($dto) {
            return $this->taskRepository->getTaskCountByUser($dto->limit);
        });
    }

    /**
     * 統計キャッシュをクリアする
     * タスクの作成・削除時に呼び出すことでキャッシュを無効化できる
     *
     * @param int|null $limit 特定のlimitのみクリアする場合は指定、nullの場合は全てクリア
     * @return void
     */
    public static function clearCache(?int $limit = null): void
    {
        if ($limit !== null) {
            // 特定のlimitのキャッシュのみクリア
            Cache::forget(self::CACHE_KEY_PREFIX . ":{$limit}");
        } else {
            // よく使われるlimitのキャッシュを全てクリア
            $commonLimits = [1, 3, 5, 10, 20, 50, 100];
            foreach ($commonLimits as $commonLimit) {
                Cache::forget(self::CACHE_KEY_PREFIX . ":{$commonLimit}");
            }
        }
    }

    /**
     * キャッシュキーを生成
     *
     * @param int $limit
     * @return string
     */
    private function getCacheKey(int $limit): string
    {
        return self::CACHE_KEY_PREFIX . ":{$limit}";
    }
}
