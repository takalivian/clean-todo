# Clean Todo - Task Management API

Clean Architecture（クリーンアーキテクチャ）の原則に基づいて設計されたタスク管理APIです。Laravel 11とPHP 8.2を使用して構築されています。

## 特徴

- **クリーンアーキテクチャ**: ドメイン駆動設計とレイヤー分離による保守性の高い設計
- **包括的なテスト**: 186個のユニット/機能テストで95%以上のカバレッジ
- **認証**: Laravel Sanctumによるトークンベース認証
- **タスク管理**: CRUD操作、フィルタリング、ソート、ページネーション
- **ソフトデリート**: タスクとユーザーの論理削除と復元
- **Docker対応**: 開発環境と本番環境の両方でDockerをサポート
- **自動デプロイ**: GitHub Actions + AWS CodeDeploy + ECRによるCI/CD

## アーキテクチャ

```
app/
├── Application/        # アプリケーション層（ユースケース、DTO）
├── Domain/            # ドメイン層（エンティティ、リポジトリインターフェース）
├── Infrastructure/    # インフラストラクチャ層（リポジトリ実装）
└── Http/             # プレゼンテーション層（コントローラ、リクエスト）
```

## API機能

### 認証
- ユーザー登録
- ログイン/ログアウト
- トークンベース認証

### タスク管理
- タスクの作成、取得、更新、削除
- タスクの完了
- 削除したタスクの復元
- フィルタリング（ステータス、ユーザー、キーワード、期限日）
- ソート（複数カラム対応）
- ページネーション

### ユーザー管理
- ユーザー一覧取得
- ユーザー情報更新
- ユーザー削除（ソフトデリート）

## 技術スタック

- **Backend**: Laravel 11, PHP 8.2
- **Database**: MySQL 8.0
- **Authentication**: Laravel Sanctum
- **Testing**: PHPUnit, Mockery
- **Containerization**: Docker, Docker Compose
- **CI/CD**: GitHub Actions, AWS CodeDeploy, Amazon ECR
- **Cloud**: AWS (EC2, ECR, S3, CodeDeploy)

## ローカル開発環境のセットアップ

### 前提条件

- Docker Desktop
- Git

### セットアップ手順

1. **リポジトリのクローン**

```bash
git clone <repository-url>
cd clean-todo
```

2. **環境変数の設定**

```bash
cp .env.example .env
```

3. **Dockerコンテナの起動**

```bash
docker compose up -d
```

4. **依存関係のインストール**

```bash
docker compose exec app composer install
```

5. **アプリケーションキーの生成**

```bash
docker compose exec app php artisan key:generate
```

6. **データベースマイグレーションの実行**

```bash
docker compose exec app php artisan migrate
```

7. **シーダーの実行（オプション）**

```bash
docker compose exec app php artisan db:seed
```

8. **アプリケーションへのアクセス**

- API Base URL: http://localhost:8080/api
- Health Check: http://localhost:8080/api/health

## テストの実行

```bash
# 全テストを実行
docker compose exec app php artisan test

# カバレッジレポート付きで実行
docker compose exec app php artisan test --coverage

# 特定のテストグループのみ実行
docker compose exec app php artisan test --filter=TaskController
```

## デプロイメント

本番環境へのデプロイについては、以下のドキュメントを参照してください：

- **[DEPLOYMENT.md](./DEPLOYMENT.md)**: 完全なデプロイメントガイド
- **[deployment/EC2_SETUP.md](./deployment/EC2_SETUP.md)**: EC2インスタンスのセットアップ

### クイックデプロイ概要

1. AWS リソースの準備（ECR、S3、CodeDeploy、EC2）
2. GitHub Secrets の設定
3. `main`または`production`ブランチへのプッシュで自動デプロイ

詳細は`DEPLOYMENT.md`を参照してください。

## API エンドポイント

### 認証（Authentication）

```
POST /api/register      - ユーザー登録
POST /api/login         - ログイン
POST /api/logout        - ログアウト（要認証）
GET  /api/user          - 現在のユーザー情報取得（要認証）
```

### タスク（Tasks）

```
GET    /api/tasks              - タスク一覧取得（フィルタリング、ページネーション対応）
POST   /api/tasks              - タスク作成
GET    /api/tasks/{id}         - タスク詳細取得
PUT    /api/tasks/{id}         - タスク更新
DELETE /api/tasks/{id}         - タスク削除（ソフトデリート）
POST   /api/tasks/{id}/complete - タスク完了
POST   /api/tasks/{id}/restore  - タスク復元
```

### ユーザー（Users）

```
GET    /api/users      - ユーザー一覧取得
PUT    /api/users/{id} - ユーザー更新
DELETE /api/users/{id} - ユーザー削除（ソフトデリート）
```

### ヘルスチェック

```
GET /api/health - アプリケーションの稼働状態確認
```

### クエリパラメータ（タスク一覧）

```
?status=0|1|2              - ステータスフィルタ（0:未着手、1:進行中、2:完了）
?user_id={id}              - ユーザーIDフィルタ
?keyword={text}            - キーワード検索（タイトル・説明）
?due_date_from={date}      - 期限日開始
?due_date_to={date}        - 期限日終了
?only_deleted=true         - 削除済みのみ取得
?with_deleted=true         - 削除済みを含めて取得
?sort_by={column}          - ソートカラム
?sort_direction=asc|desc   - ソート方向
?page={num}                - ページ番号
?per_page={num}            - 1ページあたりの件数
```

## プロジェクト構成

```
.
├── app/
│   ├── Application/          # ユースケース、DTO
│   │   ├── Task/
│   │   └── User/
│   ├── Domain/               # ドメインモデル、リポジトリインターフェース
│   │   ├── Task/
│   │   └── User/
│   ├── Http/                 # コントローラ、リクエスト
│   │   ├── Controllers/
│   │   └── Requests/
│   ├── Infrastructure/       # リポジトリ実装
│   │   ├── Task/
│   │   └── User/
│   └── Models/               # Eloquentモデル
├── database/
│   ├── migrations/          # データベースマイグレーション
│   ├── seeders/            # シーダー
│   └── factories/          # モデルファクトリー
├── deployment/
│   ├── nginx/              # Nginx設定
│   ├── supervisor/         # Supervisor設定
│   ├── scripts/            # デプロイスクリプト
│   └── EC2_SETUP.md        # EC2セットアップガイド
├── tests/
│   ├── Feature/            # 機能テスト
│   └── Unit/               # ユニットテスト
├── .github/workflows/      # GitHub Actions
├── Dockerfile              # 開発環境用
├── Dockerfile.prod         # 本番環境用
├── appspec.yml            # CodeDeploy設定
└── DEPLOYMENT.md          # デプロイメントガイド
```

## 開発ガイドライン

### コーディング規約

- PSR-12コーディングスタイルに準拠
- クリーンアーキテクチャの原則を遵守
- すべての新機能にはテストを追加

### ブランチ戦略

- `main`: 本番環境用ブランチ（自動デプロイ）
- `develop`: 開発用ブランチ
- `feature/*`: 機能開発用ブランチ

### プルリクエスト

- すべてのテストが通過していること
- コードカバレッジが維持されていること
- レビュー承認後にマージ

## トラブルシューティング

### Docker コンテナが起動しない

```bash
docker compose down -v
docker compose up -d --build
```

### データベース接続エラー

```bash
# データベースコンテナのログを確認
docker compose logs db

# マイグレーションを再実行
docker compose exec app php artisan migrate:fresh
```

### テストが失敗する

```bash
# テスト環境をリセット
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
docker compose exec app php artisan test
```

## ライセンス

このプロジェクトはMITライセンスの下で公開されています。

## サポート

問題や質問がある場合は、Issueを作成してください。
