<?php

namespace App\Console\Commands;

use App\Application\Task\DTOs\GetTasksDto;
use App\Infrastructure\Task\Repositories\EloquentTaskRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BenchmarkTaskQuery extends Command
{
    protected $signature = 'benchmark:task-query {--iterations=10 : 実行回数}';
    protected $description = 'タスク一覧取得のパフォーマンスをベンチマーク';

    public function handle()
    {
        $iterations = (int) $this->option('iterations');
        $repository = new EloquentTaskRepository();

        $this->info("タスク一覧取得のベンチマークを開始します（{$iterations}回実行）");
        $this->newLine();

        // テストケース
        $testCases = [
            [
                'name' => 'デフォルト（user_id フィルタのみ）',
                'dto' => new GetTasksDto(
                    page: 1,
                    perPage: 15,
                    status: null,
                    userId: 1,
                    keyword: null,
                    dueDateFrom: null,
                    dueDateTo: null,
                    sortBy: 'created_at',
                    sortDirection: 'desc',
                    onlyDeleted: false,
                    withDeleted: false
                ),
            ],
            [
                'name' => 'ステータスフィルタあり',
                'dto' => new GetTasksDto(
                    page: 1,
                    perPage: 15,
                    status: 1,
                    userId: 1,
                    keyword: null,
                    dueDateFrom: null,
                    dueDateTo: null,
                    sortBy: 'created_at',
                    sortDirection: 'desc',
                    onlyDeleted: false,
                    withDeleted: false
                ),
            ],
            [
                'name' => '期限日ソート',
                'dto' => new GetTasksDto(
                    page: 1,
                    perPage: 15,
                    status: null,
                    userId: null,
                    keyword: null,
                    dueDateFrom: null,
                    dueDateTo: null,
                    sortBy: 'due_date',
                    sortDirection: 'desc',
                    onlyDeleted: false,
                    withDeleted: false
                ),
            ],
            [
                'name' => '削除済み含む',
                'dto' => new GetTasksDto(
                    page: 1,
                    perPage: 15,
                    status: null,
                    userId: 1,
                    keyword: null,
                    dueDateFrom: null,
                    dueDateTo: null,
                    sortBy: 'created_at',
                    sortDirection: 'desc',
                    onlyDeleted: false,
                    withDeleted: true
                ),
            ],
        ];

        foreach ($testCases as $testCase) {
            $this->info("【{$testCase['name']}】");

            $times = [];
            $queryTimes = [];

            for ($i = 0; $i < $iterations; $i++) {
                // クエリログをクリア
                DB::flushQueryLog();
                DB::enableQueryLog();

                $start = microtime(true);
                $result = $repository->findAllWithFilter($testCase['dto']);
                $end = microtime(true);

                $times[] = ($end - $start) * 1000; // ミリ秒に変換

                // クエリ時間を集計
                $queries = DB::getQueryLog();
                $totalQueryTime = array_sum(array_column($queries, 'time'));
                $queryTimes[] = $totalQueryTime;

                DB::disableQueryLog();
            }

            // 統計を計算
            $avgTime = round(array_sum($times) / count($times), 2);
            $minTime = round(min($times), 2);
            $maxTime = round(max($times), 2);
            $avgQueryTime = round(array_sum($queryTimes) / count($queryTimes), 2);

            $this->line("  平均実行時間: {$avgTime}ms");
            $this->line("  最小実行時間: {$minTime}ms");
            $this->line("  最大実行時間: {$maxTime}ms");
            $this->line("  平均クエリ時間: {$avgQueryTime}ms");
            $this->newLine();
        }

        // 現在のインデックスを表示
        $this->info('【現在のインデックス一覧】');
        $indexes = DB::select("SHOW INDEX FROM tasks");

        $indexInfo = [];
        foreach ($indexes as $index) {
            $keyName = $index->Key_name;
            if (!isset($indexInfo[$keyName])) {
                $indexInfo[$keyName] = [];
            }
            $indexInfo[$keyName][] = $index->Column_name;
        }

        foreach ($indexInfo as $keyName => $columns) {
            $this->line("  {$keyName}: " . implode(', ', $columns));
        }

        return Command::SUCCESS;
    }
}
