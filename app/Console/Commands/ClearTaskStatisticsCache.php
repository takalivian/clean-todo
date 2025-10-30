<?php

namespace App\Console\Commands;

use App\Application\Task\UseCases\GetTaskStatisticsByUserUseCase;
use Illuminate\Console\Command;

class ClearTaskStatisticsCache extends Command
{
    protected $signature = 'cache:clear-task-statistics {--limit= : 特定のlimitのキャッシュのみクリア}';
    protected $description = 'タスク統計のキャッシュをクリアします';

    public function handle()
    {
        $limit = $this->option('limit');

        if ($limit !== null) {
            $limit = (int) $limit;
            GetTaskStatisticsByUserUseCase::clearCache($limit);
            $this->info("タスク統計のキャッシュ (limit={$limit}) をクリアしました。");
        } else {
            GetTaskStatisticsByUserUseCase::clearCache();
            $this->info('タスク統計の全キャッシュをクリアしました。');
        }

        return Command::SUCCESS;
    }
}
