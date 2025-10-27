# デプロイメントガイド

このドキュメントでは、EC2 + ECR + CodeDeployを使用した自動デプロイメントの設定と実行方法を説明します。

## アーキテクチャ概要

```
GitHub (main branch)
  ↓ push
GitHub Actions
  ↓ build & push
Amazon ECR (コンテナイメージ)
  ↓ deploy trigger
AWS CodeDeploy
  ↓ deploy
Amazon EC2 (本番環境)
```

## 前提条件

### AWS リソース

1. **ECRリポジトリ**: `clean-todo`という名前で作成
2. **S3バケット**: デプロイメントパッケージ用のバケット
3. **CodeDeploy Application**: `clean-todo-app`という名前で作成
4. **CodeDeploy Deployment Group**: `clean-todo-deployment-group`という名前で作成
5. **EC2インスタンス**: CodeDeploy Agentがインストール済み
6. **IAMロール**:
   - EC2用: ECR Pull、S3 Read、CodeDeploy権限
   - CodeDeploy用: EC2、S3アクセス権限

### GitHubリポジトリのSecrets設定

以下のSecretsをGitHubリポジトリに設定してください：

- `AWS_ACCESS_KEY_ID`: AWSアクセスキーID
- `AWS_SECRET_ACCESS_KEY`: AWSシークレットアクセスキー
- `S3_BUCKET`: デプロイメントパッケージを保存するS3バケット名

## セットアップ手順

### 1. ECRリポジトリの作成

```bash
aws ecr create-repository \
  --repository-name clean-todo \
  --region ap-northeast-1
```

### 2. S3バケットの作成

```bash
aws s3 mb s3://your-deployment-bucket --region ap-northeast-1
```

### 3. CodeDeployアプリケーションの作成

```bash
aws deploy create-application \
  --application-name clean-todo-app \
  --compute-platform Server
```

### 4. EC2用IAMロールの作成

`EC2-CodeDeploy-Role`という名前で以下のポリシーを持つロールを作成：

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "ecr:GetAuthorizationToken",
        "ecr:BatchCheckLayerAvailability",
        "ecr:GetDownloadUrlForLayer",
        "ecr:BatchGetImage"
      ],
      "Resource": "*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:ListBucket"
      ],
      "Resource": [
        "arn:aws:s3:::your-deployment-bucket/*",
        "arn:aws:s3:::your-deployment-bucket"
      ]
    },
    {
      "Effect": "Allow",
      "Action": [
        "logs:CreateLogGroup",
        "logs:CreateLogStream",
        "logs:PutLogEvents"
      ],
      "Resource": "arn:aws:logs:*:*:*"
    }
  ]
}
```

信頼関係：
```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Service": "ec2.amazonaws.com"
      },
      "Action": "sts:AssumeRole"
    }
  ]
}
```

### 5. CodeDeploy用IAMロールの作成

`CodeDeploy-ServiceRole`という名前で以下のポリシーをアタッチ：

- `AWSCodeDeployRole` (AWS管理ポリシー)

### 6. EC2インスタンスの作成と設定

詳細は`deployment/EC2_SETUP.md`を参照してください。

主な手順:
1. EC2インスタンスを起動（Amazon Linux 2023を推奨）
2. 作成したIAMロール（EC2-CodeDeploy-Role）をアタッチ
3. セキュリティグループでポート80を開放
4. SSHで接続し、必要なソフトウェアをインストール：
   - Docker
   - AWS CLI
   - CodeDeploy Agent

### 7. CodeDeploy デプロイメントグループの作成

```bash
aws deploy create-deployment-group \
  --application-name clean-todo-app \
  --deployment-group-name clean-todo-deployment-group \
  --service-role-arn arn:aws:iam::YOUR_ACCOUNT_ID:role/CodeDeploy-ServiceRole \
  --ec2-tag-filters Key=Name,Value=clean-todo-production,Type=KEY_AND_VALUE \
  --deployment-config-name CodeDeployDefault.OneAtATime
```

**注意**: EC2インスタンスに`Name=clean-todo-production`というタグを設定してください。

### 8. 環境変数の設定

EC2インスタンス上で本番環境用の環境変数ファイルを作成：

```bash
ssh ec2-user@your-ec2-ip
nano /home/ec2-user/.env.production
```

`.env.production.example`を参考に設定してください。

**重要**: `APP_KEY`は以下のコマンドで生成してください：
```bash
# ローカル環境で実行
php artisan key:generate --show
```

### 9. GitHub Secretsの設定

GitHubリポジトリの Settings → Secrets and variables → Actions で以下を設定：

- `AWS_ACCESS_KEY_ID`: デプロイ用のAWSアクセスキー
- `AWS_SECRET_ACCESS_KEY`: デプロイ用のAWSシークレットキー
- `S3_BUCKET`: 作成したS3バケット名

## デプロイ方法

### 自動デプロイ（推奨）

`main`または`production`ブランチにプッシュすると自動的にデプロイが開始されます。

```bash
git checkout main
git add .
git commit -m "Deploy to production"
git push origin main
```

### GitHub Actionsの動作確認

1. GitHubリポジトリの「Actions」タブを開く
2. 最新のワークフロー実行を確認
3. 各ステップのログを確認

### CodeDeployのデプロイ状況確認

```bash
# 最新のデプロイメントIDを取得
aws deploy list-deployments \
  --application-name clean-todo-app \
  --deployment-group-name clean-todo-deployment-group \
  --max-items 1

# デプロイメントの詳細を確認
aws deploy get-deployment \
  --deployment-id d-XXXXXXXXX
```

または、AWS Management Consoleの CodeDeploy セクションで確認できます。

## デプロイフロー詳細

### 1. GitHub Actionsによるビルドとプッシュ

1. コードをチェックアウト
2. イメージタグを生成（git SHA + タイムスタンプ）
3. AWS ECRにログイン
4. Dockerイメージをビルド（Dockerfile.prod使用）
5. ECRにイメージをプッシュ（タグ付きとlatestの両方）
6. デプロイメントパッケージを作成してS3にアップロード
7. CodeDeployデプロイメントを作成

### 2. CodeDeployによるデプロイ

CodeDeployは`appspec.yml`に従って以下の順序でスクリプトを実行：

1. **ApplicationStop** (`application_stop.sh`)
   - 既存のコンテナを停止・削除

2. **BeforeInstall** (`before_install.sh`)
   - 古いデプロイメントファイルをクリーンアップ

3. **AfterInstall** (`after_install.sh`)
   - ECRにログイン
   - 最新のDockerイメージをpull

4. **ApplicationStart** (`application_start.sh`)
   - データベースコンテナの起動（初回のみ）
   - アプリケーションコンテナの起動
   - データベースマイグレーションの実行

5. **ValidateService** (`validate_service.sh`)
   - ヘルスチェックエンドポイント（/api/health）で動作確認

## トラブルシューティング

### デプロイが失敗する場合

1. **CodeDeploy Agentのログを確認**:
```bash
ssh ec2-user@your-ec2-ip
sudo tail -f /var/log/aws/codedeploy-agent/codedeploy-agent.log
```

2. **デプロイスクリプトのログを確認**:
```bash
# 最新のデプロイメントIDを使用
sudo cat /opt/codedeploy-agent/deployment-root/deployment-logs/codedeploy-agent-deployments.log
```

3. **アプリケーションログを確認**:
```bash
docker logs clean-todo-app
```

### イメージのPullに失敗する場合

```bash
# EC2でECRへのログインを確認
aws ecr get-login-password --region ap-northeast-1 | docker login --username AWS --password-stdin <account-id>.dkr.ecr.ap-northeast-1.amazonaws.com

# IAMロールの権限を確認
aws sts get-caller-identity
```

### コンテナが起動しない場合

```bash
# コンテナのログを確認
docker logs clean-todo-app

# コンテナの状態を確認
docker ps -a

# 環境変数ファイルを確認
cat /home/ec2-user/.env.production
```

### データベース接続エラー

```bash
# データベースコンテナが起動しているか確認
docker ps | grep clean-todo-db

# データベースコンテナのログを確認
docker logs clean-todo-db

# ネットワークを確認
docker network inspect clean-todo-network
```

### ヘルスチェックが失敗する場合

```bash
# EC2上でヘルスチェックエンドポイントに手動アクセス
curl http://localhost/api/health

# Nginxのログを確認
docker exec clean-todo-app tail -f /var/log/nginx/error.log
```

## ロールバック

デプロイに問題がある場合、以前のバージョンに戻すことができます：

```bash
# 以前のデプロイメントIDを確認
aws deploy list-deployments \
  --application-name clean-todo-app \
  --deployment-group-name clean-todo-deployment-group

# 特定のバージョンに再デプロイ
# 1. ECRから以前のイメージタグを確認
aws ecr list-images --repository-name clean-todo

# 2. EC2でそのイメージを手動でpullして起動
ssh ec2-user@your-ec2-ip
docker pull <account-id>.dkr.ecr.ap-northeast-1.amazonaws.com/clean-todo:<previous-tag>
docker stop clean-todo-app
docker rm clean-todo-app
docker run -d --name clean-todo-app ... <previous-tag>
```

## セキュリティベストプラクティス

1. **環境変数の管理**
   - 本番環境の`.env.production`ファイルは決してリポジトリにコミットしない
   - 機密情報はAWS Secrets ManagerまたはParameter Storeの使用を検討

2. **IAMロールの最小権限**
   - 必要最小限の権限のみを付与

3. **ネットワークセキュリティ**
   - セキュリティグループで必要なポートのみを開放
   - 可能であればVPC内での通信を推奨

4. **HTTPSの設定**
   - Application Load BalancerまたはCloudFrontを使用してSSL/TLS終端を設定
   - Let's Encryptで無料のSSL証明書を取得可能

5. **ログとモニタリング**
   - CloudWatch Logsでログを集約
   - CloudWatch Alarmsでエラーを監視

## 参考リンク

- [AWS CodeDeploy Documentation](https://docs.aws.amazon.com/codedeploy/)
- [Amazon ECR Documentation](https://docs.aws.amazon.com/ecr/)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Laravel Deployment Documentation](https://laravel.com/docs/deployment)
