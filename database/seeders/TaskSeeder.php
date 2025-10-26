<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ユーザーを取得（UserSeederが先に実行されている前提）
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->command->warn('ユーザーが見つかりません。先にUserSeederを実行してください。');
            return;
        }

        $tasks = [
            // 未着手タスク
            ['title' => 'プロジェクト企画書を作成', 'description' => '新規プロジェクトの企画書を作成し、チームに共有する', 'status' => 0, 'due_date' => Carbon::now()->addDays(3)],
            ['title' => 'クライアントとのミーティング', 'description' => '来週のプロジェクトキックオフについて打ち合わせ', 'status' => 0, 'due_date' => Carbon::now()->addDays(7)],
            ['title' => 'APIドキュメント作成', 'description' => 'RESTful APIの仕様書を作成し、Swagger形式で公開', 'status' => 0, 'due_date' => Carbon::now()->addDays(10)],
            ['title' => 'ユーザーマニュアル更新', 'description' => '最新機能を反映したマニュアルに更新', 'status' => 0, 'due_date' => Carbon::now()->addDays(6)],
            ['title' => 'リリースノート作成', 'description' => 'v2.0のリリースノートを作成し、チームレビューを受ける', 'status' => 0, 'due_date' => Carbon::now()->addDays(2)],
            ['title' => '予算計画書の作成', 'description' => '次年度の予算計画書をまとめる', 'status' => 0, 'due_date' => Carbon::now()->addDays(15)],
            ['title' => 'マーケティング資料の準備', 'description' => '新製品発表会用の資料を準備する', 'status' => 0, 'due_date' => Carbon::now()->addDays(12)],
            ['title' => '新入社員研修資料作成', 'description' => '4月入社の新入社員向け研修資料を作成', 'status' => 0, 'due_date' => Carbon::now()->addDays(20)],
            ['title' => 'サーバー監視設定', 'description' => '本番環境のサーバー監視を設定する', 'status' => 0, 'due_date' => Carbon::now()->addDays(8)],
            ['title' => 'ブログ記事執筆', 'description' => '技術ブログに新機能の紹介記事を投稿', 'status' => 0, 'due_date' => Carbon::now()->addDays(5)],

            // 進行中タスク
            ['title' => 'データベース設計', 'description' => '新機能のためのデータベーススキーマを設計する', 'status' => 1, 'due_date' => Carbon::now()->addDays(5)],
            ['title' => 'ユニットテスト実装', 'description' => '新規機能のユニットテストを作成し、カバレッジ80%以上を目指す', 'status' => 1, 'due_date' => Carbon::now()->addDays(4)],
            ['title' => 'パフォーマンス最適化', 'description' => 'ページロード時間を2秒以内に改善する', 'status' => 1, 'due_date' => Carbon::now()->addDays(14)],
            ['title' => 'フロントエンド実装', 'description' => 'React.jsで新しいダッシュボード画面を実装', 'status' => 1, 'due_date' => Carbon::now()->addDays(9)],
            ['title' => 'CI/CDパイプライン構築', 'description' => 'GitHub Actionsを使った自動デプロイ環境を構築', 'status' => 1, 'due_date' => Carbon::now()->addDays(11)],
            ['title' => 'ログ分析システム構築', 'description' => 'ELKスタックを使ったログ分析環境を構築', 'status' => 1, 'due_date' => Carbon::now()->addDays(13)],
            ['title' => 'モバイルアプリ開発', 'description' => 'iOSとAndroid版のアプリを開発', 'status' => 1, 'due_date' => Carbon::now()->addDays(30)],
            ['title' => 'コードレビュー実施', 'description' => 'チームメンバーのプルリクエストをレビュー', 'status' => 1, 'due_date' => Carbon::now()->addDays(1)],

            // 完了タスク
            ['title' => 'セキュリティ監査', 'description' => 'アプリケーションのセキュリティ脆弱性をチェック', 'status' => 2, 'due_date' => Carbon::now()->subDays(2), 'completed_at' => Carbon::now()->subDays(1)],
            ['title' => 'バックアップシステム構築', 'description' => '自動バックアップシステムを構築し、定期実行を設定', 'status' => 2, 'due_date' => Carbon::now()->subDays(5), 'completed_at' => Carbon::now()->subDays(4)],
            ['title' => 'SSL証明書更新', 'description' => '本番環境のSSL証明書を更新', 'status' => 2, 'due_date' => Carbon::now()->subDays(3), 'completed_at' => Carbon::now()->subDays(3)],
            ['title' => 'ドメイン更新手続き', 'description' => '会社ドメインの更新手続きを完了', 'status' => 2, 'due_date' => Carbon::now()->subDays(10), 'completed_at' => Carbon::now()->subDays(8)],
            ['title' => '月次レポート作成', 'description' => '先月のプロジェクト進捗レポートを作成', 'status' => 2, 'due_date' => Carbon::now()->subDays(1), 'completed_at' => Carbon::now()->subHours(12)],
            ['title' => 'チームミーティング実施', 'description' => '週次のチームミーティングを実施', 'status' => 2, 'due_date' => Carbon::now()->subDays(2), 'completed_at' => Carbon::now()->subDays(2)],
            ['title' => 'サーバーメンテナンス', 'description' => 'データベースサーバーの定期メンテナンス', 'status' => 2, 'due_date' => Carbon::now()->subDays(7), 'completed_at' => Carbon::now()->subDays(6)],
            ['title' => 'ライブラリアップデート', 'description' => '依存ライブラリを最新版にアップデート', 'status' => 2, 'due_date' => Carbon::now()->subDays(4), 'completed_at' => Carbon::now()->subDays(3)],

            // 削除済みタスク
            ['title' => '旧機能の削除', 'description' => '使われていない旧機能をコードベースから削除', 'status' => 0, 'due_date' => Carbon::now()->addDays(5), 'deleted_at' => Carbon::now()->subDays(1)],
            ['title' => '重複タスク', 'description' => 'これは重複して作成されたタスクです', 'status' => 0, 'due_date' => Carbon::now()->addDays(3), 'deleted_at' => Carbon::now()->subHours(6)],
            ['title' => 'キャンセルされたミーティング', 'description' => 'クライアントの都合でキャンセル', 'status' => 0, 'due_date' => Carbon::now()->addDays(2), 'deleted_at' => Carbon::now()->subDays(2)],
            ['title' => '不要になった機能開発', 'description' => '要件変更により不要になった', 'status' => 1, 'due_date' => Carbon::now()->addDays(10), 'deleted_at' => Carbon::now()->subDays(3)],
        ];

        foreach ($tasks as $taskData) {
            // ランダムなユーザーを選択（タスクの所有者）
            $randomUser = $users->random();
            // ランダムなユーザーを選択（更新者）
            $updater = $users->random();

            $task = Task::create([
                'title' => $taskData['title'],
                'description' => $taskData['description'],
                'status' => $taskData['status'],
                'due_date' => $taskData['due_date'],
                'completed_at' => $taskData['completed_at'] ?? null,
                'user_id' => $randomUser->id,
                'updated_by' => $updater->id,
            ]);

            // 削除済みタスクの場合は削除処理を実行
            if (isset($taskData['deleted_at'])) {
                $task->delete();
                // deleted_atを指定の日時に更新
                $task->deleted_at = $taskData['deleted_at'];
                $task->save();
            }
        }
    }
}
