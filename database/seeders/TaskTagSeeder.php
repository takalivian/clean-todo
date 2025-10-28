<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TaskTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // タグIDを取得
        $tagIds = \App\Models\Tag::pluck('id')->toArray();

        if (empty($tagIds)) {
            echo "タグが存在しません。先にTagSeederを実行してください。\n";
            return;
        }

        $taskCount = \App\Models\Task::count();
        echo "処理対象タスク数: {$taskCount}\n";

        $attachedCount = 0;
        $processedTasks = 0;

        // チャンク処理でタスクを処理
        \App\Models\Task::chunk(1000, function ($tasks) use ($tagIds, &$attachedCount, &$processedTasks, $taskCount) {
            $attachments = [];

            foreach ($tasks as $task) {
                // ランダムにタグを付けるかどうか決定（60%の確率でタグを付ける）
                if (rand(1, 100) <= 60) {
                    // ランダムに1〜3個のタグを選択
                    $numberOfTags = rand(1, 3);
                    $selectedTags = array_rand(array_flip($tagIds), min($numberOfTags, count($tagIds)));

                    // array_randが1つの場合は配列にならないので配列化
                    if (!is_array($selectedTags)) {
                        $selectedTags = [$selectedTags];
                    }

                    foreach ($selectedTags as $tagId) {
                        $attachments[] = [
                            'task_id' => $task->id,
                            'tag_id' => $tagId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $attachedCount++;
                    }
                }

                $processedTasks++;
            }

            // バッチ挿入
            if (!empty($attachments)) {
                \DB::table('task_tag')->insert($attachments);
            }

            // 進捗表示
            $progress = round(($processedTasks / $taskCount) * 100, 1);
            echo "進捗: {$progress}% ({$processedTasks}/{$taskCount}) - 紐付け数: {$attachedCount}\n";
        });

        echo "完了: {$processedTasks}件のタスクを処理し、{$attachedCount}件の紐付けを作成しました。\n";
    }
}
