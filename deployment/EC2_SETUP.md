# EC2セットアップガイド

このドキュメントでは、EC2インスタンスで本アプリケーションをホストするための初期セットアップ手順を説明します。

## 前提条件

- AWS EC2インスタンス（Amazon Linux 2023または Amazon Linux 2を推奨）
- インスタンスにIAMロールが設定されている（ECR、S3、CodeDeployへのアクセス権限）
- セキュリティグループで必要なポート（80, 443, 3306）が開放されている

## 1. EC2インスタンスへの接続

```bash
ssh -i /path/to/your-key.pem ec2-user@your-ec2-public-ip
```

## 2. システムパッケージの更新

```bash
sudo yum update -y
```

## 3. Dockerのインストール

```bash
# Dockerをインストール
sudo yum install -y docker

# Dockerサービスを起動
sudo systemctl start docker
sudo systemctl enable docker

# ec2-userをdockerグループに追加
sudo usermod -a -G docker ec2-user

# 変更を反映させるため、一度ログアウトして再ログイン
exit
# 再度SSHで接続
```

## 4. Docker Composeのインストール（オプション）

```bash
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
docker-compose --version
```

## 5. AWS CLI v2のインストール

```bash
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
unzip awscliv2.zip
sudo ./aws/install
aws --version
```

## 6. CodeDeploy Agentのインストール

```bash
# Amazon Linux 2023の場合
sudo yum install -y ruby wget
cd /home/ec2-user
wget https://aws-codedeploy-ap-northeast-1.s3.ap-northeast-1.amazonaws.com/latest/install
chmod +x ./install
sudo ./install auto

# CodeDeploy Agentのステータス確認
sudo systemctl status codedeploy-agent
sudo systemctl enable codedeploy-agent
```

## 7. 環境変数ファイルの設置

本番環境用の環境変数ファイルを作成します。

```bash
# .env.productionファイルを作成
nano /home/ec2-user/.env.production
```

`.env.production.example`を参考に、適切な値を設定してください。

**重要な設定項目:**
- `APP_KEY`: `php artisan key:generate`で生成したキーを設定
- `DB_PASSWORD`: セキュアなパスワードを設定
- `DB_ROOT_PASSWORD`: セキュアなルートパスワードを設定
- `APP_URL`: 実際のドメインまたはIPアドレス

## 8. Dockerネットワークの作成

```bash
docker network create clean-todo-network
```

## 9. IAMロールの設定確認

EC2インスタンスに以下の権限を持つIAMロールがアタッチされていることを確認してください。

### 必要な権限:

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
    }
  ]
}
```

## 10. セキュリティグループの設定

EC2インスタンスのセキュリティグループで以下のポートを開放してください。

- **80 (HTTP)**: アプリケーションへのアクセス
- **443 (HTTPS)**: SSL接続（設定する場合）
- **22 (SSH)**: 管理アクセス（必要な場合のみ、信頼できるIPからのみ）

## 11. デプロイの準備完了確認

```bash
# Dockerが動作していることを確認
docker ps

# AWS CLIが設定されていることを確認
aws sts get-caller-identity

# CodeDeploy Agentが動作していることを確認
sudo systemctl status codedeploy-agent
```

すべて正常であれば、CodeDeployによる自動デプロイの準備が完了です。

## トラブルシューティング

### CodeDeploy Agentが起動しない場合

```bash
# ログを確認
sudo tail -f /var/log/aws/codedeploy-agent/codedeploy-agent.log

# エージェントを再起動
sudo systemctl restart codedeploy-agent
```

### Dockerの権限エラー

```bash
# ec2-userがdockerグループに所属しているか確認
groups

# 所属していない場合は追加して再ログイン
sudo usermod -a -G docker ec2-user
exit
```

### ECRへのログイン失敗

```bash
# IAMロールの権限を確認
aws sts get-caller-identity

# ECRへの手動ログイン
aws ecr get-login-password --region ap-northeast-1 | docker login --username AWS --password-stdin <account-id>.dkr.ecr.ap-northeast-1.amazonaws.com
```
