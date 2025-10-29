<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LargeDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 大量データを生成して負荷テスト・DBチューニングの練習用
     */
    public function run(): void
    {
        $this->command->info('Starting large data generation...');

        // トランザクションを使用してパフォーマンスを向上
        \DB::transaction(function () {
            // 1. ユーザーを10,000人生成
            $this->command->info('Creating 1,000 users...');
            $userChunks = 10; // チャンク数
            $usersPerChunk = 1000; // 1チャンクあたりのユーザー数

            for ($i = 0; $i < $userChunks; $i++) {
                $users = \App\Models\User::factory($usersPerChunk)->create();
                $this->command->info("Created " . (($i + 1) * $usersPerChunk) . " users");
            }

            $this->command->info('Users created successfully!');

            // 2. タスクを1,000,000件生成（Factory使用）
            $this->command->info('Creating 200,000 tasks using Factory...');
            $taskChunks = 2000; // チャンク数
            $tasksPerChunk = 100; // 1チャンクあたりのタスク数（プレースホルダー上限対策）

            $userIds = \App\Models\User::pluck('id')->toArray();

            for ($i = 0; $i < $taskChunks; $i++) {
                // Factoryを使用してランダムなタスクを生成（より効率的）
                $tasks = [];
                for ($j = 0; $j < $tasksPerChunk; $j++) {
                    $task = \App\Models\Task::factory()->make([
                        'user_id' => $userIds[array_rand($userIds)],
                    ]);

                    // アクセサをバイパスして生の属性を取得
                    $taskArray = $task->getAttributes();
                    if (isset($taskArray['due_date']) && $taskArray['due_date']) {
                        $taskArray['due_date'] = \Carbon\Carbon::parse($taskArray['due_date'])->format('Y-m-d H:i:s');
                    }
                    if (isset($taskArray['completed_at']) && $taskArray['completed_at']) {
                        $taskArray['completed_at'] = \Carbon\Carbon::parse($taskArray['completed_at'])->format('Y-m-d H:i:s');
                    }
                    if (isset($taskArray['created_at']) && $taskArray['created_at']) {
                        $taskArray['created_at'] = \Carbon\Carbon::parse($taskArray['created_at'])->format('Y-m-d H:i:s');
                    }
                    if (isset($taskArray['updated_at']) && $taskArray['updated_at']) {
                        $taskArray['updated_at'] = \Carbon\Carbon::parse($taskArray['updated_at'])->format('Y-m-d H:i:s');
                    }

                    $tasks[] = $taskArray;
                }

                \App\Models\Task::insert($tasks);

                if (($i + 1) % 100 == 0) {
                    $this->command->info("Created " . (($i + 1) * $tasksPerChunk) . " tasks");
                }
            }

            $this->command->info('Tasks created successfully!');
        });

        $this->command->info('Large data generation completed!');
        $this->command->info('Total Users: ' . \App\Models\User::count());
        $this->command->info('Total Tasks: ' . \App\Models\Task::count());
    }
}
