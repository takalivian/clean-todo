# 負荷テスト・DBチューニング練習ガイド

## セットアップ

### 1. 大量テストデータの生成

```bash
# 既存データをクリアして大量データを生成
docker compose exec app php artisan migrate:fresh
docker compose exec app php artisan db:seed --class=LargeDataSeeder
```

**生成されるデータ：**
- ユーザー: 10,000人
- タスク: 100,000件

⚠️ 注意: 生成には数分かかります（約5-10分）

### 2. MySQLログの有効化

スロークエリログとクエリログを有効化するため、Docker-composeを再起動：

```bash
docker compose down
docker compose up -d
```

**ログファイルの場所：**
- スロークエリログ: `storage/mysql-logs/mysql-slow.log`
- クエリログ: `storage/mysql-logs/mysql-query.log`

## 負荷テストの実行

### Apache Benchを使用した負荷テスト

```bash
# 基本的な負荷テスト（100リクエスト、同時実行10）
ab -n 100 -c 10 http://localhost:8080/api/tasks

# より高負荷（1000リクエスト、同時実行50）
ab -n 1000 -c 50 http://localhost:8080/api/tasks

# 認証が必要なエンドポイントのテスト
ab -n 100 -c 10 -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8080/api/tasks
```

### wrk（高性能負荷テストツール）

```bash
# インストール（macOS）
brew install wrk

# 負荷テスト実行（30秒間、10スレッド、100コネクション）
wrk -t10 -c100 -d30s http://localhost:8080/api/tasks
```

## DBチューニングの練習

### 1. スロークエリの確認

```bash
# スロークエリログを確認
tail -f storage/mysql-logs/mysql-slow.log

# または
docker compose exec db cat /var/log/mysql/mysql-slow.log
```

### 2. EXPLAIN で実行計画を確認

```bash
docker compose exec app php artisan tinker

# Tinker内で実行
DB::enableQueryLog();
\App\Models\Task::where('user_id', 1)->get();
DB::getQueryLog();

# EXPLAINを使った分析
DB::select('EXPLAIN SELECT * FROM tasks WHERE user_id = 1');
```

### 3. インデックスの追加

```bash
# マイグレーションを作成
docker compose exec app php artisan make:migration add_indexes_to_tasks_table

# インデックスを追加（例）
Schema::table('tasks', function (Blueprint $table) {
    $table->index('user_id');
    $table->index('completed_at');
    $table->index(['user_id', 'completed_at']);
});
```

### 4. クエリの最適化を確認

```bash
# インデックス追加前
docker compose exec app php artisan tinker --execute="\$start = microtime(true); \App\Models\Task::where('user_id', 1)->get(); echo 'Time: ' . (microtime(true) - \$start) . 's';"

# インデックス追加後に再度実行して比較
docker compose exec app php artisan migrate

docker compose exec app php artisan tinker --execute="\$start = microtime(true); \App\Models\Task::where('user_id', 1)->get(); echo 'Time: ' . (microtime(true) - \$start) . 's';"
```

## よくある最適化パターン

### N+1問題の検出と解決

```php
// 問題のあるコード
$tasks = Task::all();
foreach ($tasks as $task) {
    echo $task->user->name; // N+1問題
}

// 解決策
$tasks = Task::with('user')->get();
foreach ($tasks as $task) {
    echo $task->user->name; // 1回のクエリで解決
}
```

### ページネーション

```php
// 大量データの場合はページネーションを使用
Task::paginate(50);

// cursorベースのページネーション（より効率的）
Task::cursorPaginate(50);
```

### Eager Loading

```php
// 複数のリレーションをロード
Task::with(['user', 'tags'])->get();

// 条件付きEager Loading
Task::with(['user' => function ($query) {
    $query->select('id', 'name');
}])->get();
```

## パフォーマンス測定コマンド

```bash
# Laravelのクエリログを有効化してリクエスト
docker compose exec app php artisan tinker --execute="DB::enableQueryLog(); \App\Models\Task::with('user')->limit(100)->get(); print_r(DB::getQueryLog());"

# データベースのテーブルサイズを確認
docker compose exec db mysql -u root -prootsecret -e "SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)' FROM information_schema.TABLES WHERE table_schema = 'laravel' ORDER BY (data_length + index_length) DESC;"

# インデックスの一覧を確認
docker compose exec db mysql -u root -prootsecret -e "SHOW INDEX FROM laravel.tasks;"
```

## 練習課題

1. **スロークエリの特定**: 0.1秒以上かかるクエリを見つける
2. **インデックス追加**: user_idとcompleted_atにインデックスを追加して速度を比較
3. **N+1問題の解決**: タスク一覧とユーザー情報を効率的に取得
4. **複合インデックス**: 複数カラムを使った検索の最適化
5. **キャッシュの導入**: Redis等を使った結果のキャッシング

## トラブルシューティング

### ログファイルが作成されない場合

```bash
# コンテナを再作成
docker compose down
docker compose up -d

# ログディレクトリの権限を確認
ls -la storage/mysql-logs/

# 手動でログファイルを作成
touch storage/mysql-logs/mysql-slow.log
touch storage/mysql-logs/mysql-query.log
chmod 666 storage/mysql-logs/*.log
```

### メモリ不足の場合

docker-compose.ymlでMySQLのメモリ制限を調整：

```yaml
db:
  deploy:
    resources:
      limits:
        memory: 2G
```
