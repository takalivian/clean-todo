<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tagNames = [
            '重要', '緊急', '優先度高', '優先度低', '確認中',
            '保留', '進行中', '完了予定', 'バグ', '機能追加',
            '改善', 'ドキュメント', 'テスト', 'レビュー待ち', 'デプロイ',
            'フロントエンド', 'バックエンド', 'データベース', 'セキュリティ', 'パフォーマンス',
            '個人', '仕事', '趣味', '買い物', '学習',
            '健康', '運動', '料理', '旅行', '読書'
        ];

        return [
            'name' => fake()->unique()->randomElement($tagNames),
            'user_id' => \App\Models\User::factory(),
            'updated_by' => null,
        ];
    }
}
