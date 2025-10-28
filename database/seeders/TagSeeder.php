<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 既存のユーザーIDを取得（大量データ対応のため limit を使用）
        $userIds = \App\Models\User::limit(100)->pluck('id')->toArray();

        if (empty($userIds)) {
            echo "ユーザーが存在しません。先にユーザーを作成してください。\n";
            return;
        }

        $tagNames = [
            '重要', '緊急', '優先度高', '優先度低', '確認中',
            '保留', '進行中', '完了予定', 'バグ', '機能追加',
            '改善', 'ドキュメント', 'テスト', 'レビュー待ち', 'デプロイ',
            'フロントエンド', 'バックエンド', 'データベース', 'セキュリティ', 'パフォーマンス',
            '個人', '仕事', '趣味', '買い物', '学習',
            '健康', '運動', '料理', '旅行', '読書'
        ];

        $created = 0;

        // 各タグ名に対してランダムなユーザーで作成
        foreach ($tagNames as $tagName) {
            $randomUserId = $userIds[array_rand($userIds)];

            \App\Models\Tag::create([
                'name' => $tagName,
                'user_id' => $randomUserId,
                'updated_by' => null,
            ]);

            $created++;
        }

        echo "タグを {$created}件作成しました。\n";
    }
}
