# クイックスタートガイド

このガイドでは、デプロイメントを最速で完了させる手順を説明します。

## 前提条件

✅ GitHub Secretsの設定が完了していること
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `S3_BUCKET`

## ステップ1: AWSリソースの作成（約10分）

ローカル環境のターミナルで実行：

```bash
cd /Users/takamura/products/private/clean-todo

# 環境変数を設定
export AWS_REGION=ap-northeast-1
export AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
export PROJECT_NAME=clean-todo
export S3_BUCKET=${PROJECT_NAME}-deployment
export EC2_KEY_NAME=${PROJECT_NAME}-keypair
export EC2_INSTANCE_TYPE=t3.micro

# VPC IDを取得
VPC_ID=$(aws ec2 describe-vpcs --filters "Name=isDefault,Values=true" --query 'Vpcs[0].VpcId' --output text)

# 1. ECRリポジトリを作成
echo "Creating ECR repository..."
aws ecr create-repository --repository-name ${PROJECT_NAME} --region ${AWS_REGION}

# 2. S3バケットを作成
echo "Creating S3 bucket..."
aws s3 mb s3://${S3_BUCKET} --region ${AWS_REGION}

# 3. EC2用IAMロールを作成
echo "Creating EC2 IAM role..."
cat > /tmp/ec2-trust-policy.json << 'EOF'
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Principal": {"Service": "ec2.amazonaws.com"},
    "Action": "sts:AssumeRole"
  }]
}
EOF

aws iam create-role \
  --role-name ${PROJECT_NAME}-EC2-Role \
  --assume-role-policy-document file:///tmp/ec2-trust-policy.json

cat > /tmp/ec2-policy.json << EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["ecr:*"],
      "Resource": "*"
    },
    {
      "Effect": "Allow",
      "Action": ["s3:GetObject", "s3:ListBucket"],
      "Resource": ["arn:aws:s3:::${S3_BUCKET}/*", "arn:aws:s3:::${S3_BUCKET}"]
    },
    {
      "Effect": "Allow",
      "Action": ["logs:*"],
      "Resource": "*"
    }
  ]
}
EOF

aws iam put-role-policy \
  --role-name ${PROJECT_NAME}-EC2-Role \
  --policy-name ${PROJECT_NAME}-EC2-Policy \
  --policy-document file:///tmp/ec2-policy.json

aws iam create-instance-profile --instance-profile-name ${PROJECT_NAME}-EC2-InstanceProfile
aws iam add-role-to-instance-profile \
  --instance-profile-name ${PROJECT_NAME}-EC2-InstanceProfile \
  --role-name ${PROJECT_NAME}-EC2-Role

# 少し待機（IAMロールの伝播）
echo "Waiting for IAM role to propagate..."
sleep 10

# 4. CodeDeploy用IAMロールを作成
echo "Creating CodeDeploy IAM role..."
cat > /tmp/codedeploy-trust-policy.json << 'EOF'
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Principal": {"Service": "codedeploy.amazonaws.com"},
    "Action": "sts:AssumeRole"
  }]
}
EOF

aws iam create-role \
  --role-name ${PROJECT_NAME}-CodeDeploy-Role \
  --assume-role-policy-document file:///tmp/codedeploy-trust-policy.json

aws iam attach-role-policy \
  --role-name ${PROJECT_NAME}-CodeDeploy-Role \
  --policy-arn arn:aws:iam::aws:policy/AWSCodeDeployRole

CODEDEPLOY_ROLE_ARN=$(aws iam get-role --role-name ${PROJECT_NAME}-CodeDeploy-Role --query 'Role.Arn' --output text)

# 5. セキュリティグループを作成
echo "Creating security group..."
SECURITY_GROUP_ID=$(aws ec2 create-security-group \
  --group-name ${PROJECT_NAME}-sg \
  --description "Security group for ${PROJECT_NAME}" \
  --vpc-id ${VPC_ID} \
  --query 'GroupId' --output text)

aws ec2 authorize-security-group-ingress --group-id ${SECURITY_GROUP_ID} --protocol tcp --port 80 --cidr 0.0.0.0/0
aws ec2 authorize-security-group-ingress --group-id ${SECURITY_GROUP_ID} --protocol tcp --port 443 --cidr 0.0.0.0/0
aws ec2 authorize-security-group-ingress --group-id ${SECURITY_GROUP_ID} --protocol tcp --port 22 --cidr 0.0.0.0/0

# 6. EC2キーペアを作成
echo "Creating EC2 key pair..."
aws ec2 create-key-pair --key-name ${EC2_KEY_NAME} --query 'KeyMaterial' --output text > ${EC2_KEY_NAME}.pem
chmod 400 ${EC2_KEY_NAME}.pem

# 7. EC2インスタンスを起動
echo "Launching EC2 instance..."
AMI_ID=$(aws ec2 describe-images \
  --owners amazon \
  --filters "Name=name,Values=al2023-ami-2023.*-x86_64" "Name=state,Values=available" \
  --query 'sort_by(Images, &CreationDate)[-1].ImageId' --output text)

INSTANCE_ID=$(aws ec2 run-instances \
  --image-id ${AMI_ID} \
  --instance-type ${EC2_INSTANCE_TYPE} \
  --key-name ${EC2_KEY_NAME} \
  --security-group-ids ${SECURITY_GROUP_ID} \
  --iam-instance-profile Name=${PROJECT_NAME}-EC2-InstanceProfile \
  --tag-specifications "ResourceType=instance,Tags=[{Key=Name,Value=${PROJECT_NAME}-production}]" \
  --query 'Instances[0].InstanceId' --output text)

echo "Waiting for instance to start..."
aws ec2 wait instance-running --instance-ids ${INSTANCE_ID}

PUBLIC_IP=$(aws ec2 describe-instances \
  --instance-ids ${INSTANCE_ID} \
  --query 'Reservations[0].Instances[0].PublicIpAddress' --output text)

# 8. CodeDeployアプリケーションを作成
echo "Creating CodeDeploy application..."
aws deploy create-application --application-name ${PROJECT_NAME}-app --compute-platform Server

# 9. CodeDeployデプロイメントグループを作成
echo "Creating CodeDeploy deployment group..."
aws deploy create-deployment-group \
  --application-name ${PROJECT_NAME}-app \
  --deployment-group-name ${PROJECT_NAME}-deployment-group \
  --service-role-arn ${CODEDEPLOY_ROLE_ARN} \
  --ec2-tag-filters Key=Name,Value=${PROJECT_NAME}-production,Type=KEY_AND_VALUE \
  --deployment-config-name CodeDeployDefault.OneAtATime

# クリーンアップ
rm /tmp/ec2-trust-policy.json /tmp/ec2-policy.json /tmp/codedeploy-trust-policy.json

# セットアップ情報を出力
echo ""
echo "================================"
echo "✅ AWS Setup Complete!"
echo "================================"
echo "S3 Bucket: ${S3_BUCKET}"
echo "EC2 Instance ID: ${INSTANCE_ID}"
echo "EC2 Public IP: ${PUBLIC_IP}"
echo "SSH Key: ${EC2_KEY_NAME}.pem"
echo "================================"
echo ""
echo "⚠️  IMPORTANT: Update GitHub Secret"
echo "S3_BUCKET should be set to: ${S3_BUCKET}"
echo ""
echo "Next: Run Step 2 to setup EC2 instance"
echo "ssh -i ${EC2_KEY_NAME}.pem ec2-user@${PUBLIC_IP}"
```

## ステップ2: EC2インスタンスのセットアップ（約5分）

### 2.1 EC2に接続

```bash
# ステップ1で出力されたコマンドを使用
ssh -i clean-todo-key.pem ec2-user@<PUBLIC_IP>
```

### 2.2 セットアップスクリプトを実行

EC2内で以下のコマンドを実行：

```bash
# システム更新
sudo yum update -y

# Dockerインストール
sudo yum install -y docker
sudo systemctl start docker
sudo systemctl enable docker
sudo usermod -a -G docker ec2-user

# Docker Composeインストール
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# AWS CLI v2インストール
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
unzip awscliv2.zip
sudo ./aws/install
rm -rf aws awscliv2.zip

# CodeDeploy Agentインストール
sudo yum install -y ruby wget
cd /home/ec2-user
wget https://aws-codedeploy-ap-northeast-1.s3.ap-northeast-1.amazonaws.com/latest/install
chmod +x ./install
sudo ./install auto
rm install
sudo systemctl enable codedeploy-agent
sudo systemctl start codedeploy-agent

# Dockerネットワーク作成
docker network create clean-todo-network 2>/dev/null || true

# バックアップディレクトリ作成
mkdir -p /home/ec2-user/backups

echo "Setup complete! Please logout and login again."
```

### 2.3 再ログイン

```bash
exit
ssh -i clean-todo-key.pem ec2-user@<PUBLIC_IP>
```

### 2.4 動作確認

```bash
docker ps
aws --version
sudo systemctl status codedeploy-agent
```

## ステップ3: 環境変数ファイルの作成（約2分）

EC2内で`.env.production`ファイルを作成：

```bash
nano /home/ec2-user/.env.production
```

以下の内容をコピーして貼り付け（**値は適宜変更**）：

```env
APP_NAME="Clean Todo"
APP_ENV=production
APP_KEY=base64:ここにphp artisan key:generate --showで生成したキーを貼り付け
APP_DEBUG=false
APP_URL=http://<EC2のパブリックIP>

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=clean-todo-db
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=ここに強力なパスワードを設定
DB_ROOT_PASSWORD=ここに強力なルートパスワードを設定

CACHE_DRIVER=file
SESSION_DRIVER=cookie
SESSION_LIFETIME=120

SANCTUM_STATEFUL_DOMAINS=<EC2のパブリックIP>
```

**APP_KEYの生成方法**（ローカル環境で実行）：
```bash
cd /Users/takamura/products/private/clean-todo
docker compose exec app php artisan key:generate --show
```

保存して終了（`Ctrl+X` → `Y` → `Enter`）

## ステップ4: デプロイの実行（約3分）

ローカル環境で：

```bash
cd /Users/takamura/products/private/clean-todo

# 変更をコミット
git add .
git commit -m "Setup deployment configuration"
git push origin main
```

GitHub Actionsでデプロイが自動実行されます。

### デプロイ状況の確認

1. **GitHub Actionsの確認**
   - GitHubリポジトリの「Actions」タブで進行状況を確認

2. **CodeDeployの確認**
   ```bash
   aws deploy list-deployments \
     --application-name clean-todo-app \
     --deployment-group-name clean-todo-deployment-group
   ```

3. **EC2でログ確認**
   ```bash
   ssh -i clean-todo-key.pem ec2-user@<PUBLIC_IP>

   # CodeDeployのログ
   sudo tail -f /var/log/aws/codedeploy-agent/codedeploy-agent.log

   # アプリケーションのログ
   docker logs clean-todo-app
   ```

## ステップ5: 動作確認（約1分）

```bash
# ヘルスチェック
curl http://<EC2のパブリックIP>/api/health

# ユーザー登録テスト
curl -X POST http://<EC2のパブリックIP>/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

成功すれば、デプロイ完了です！🎉

## トラブルシューティング

### デプロイが失敗する場合

```bash
# EC2のCodeDeployログを確認
ssh -i clean-todo-key.pem ec2-user@<PUBLIC_IP>
sudo tail -100 /var/log/aws/codedeploy-agent/codedeploy-agent.log

# デプロイスクリプトのログ
sudo cat /opt/codedeploy-agent/deployment-root/deployment-logs/codedeploy-agent-deployments.log
```

### コンテナが起動しない場合

```bash
# コンテナの状態確認
docker ps -a

# ログ確認
docker logs clean-todo-app
docker logs clean-todo-db

# 環境変数確認
cat /home/ec2-user/.env.production
```

### GitHub Secretsを確認

GitHub リポジトリの Settings → Secrets で以下が正しく設定されているか確認：
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `S3_BUCKET` = `clean-todo-deployment-<AWS_ACCOUNT_ID>`

## 次回以降のデプロイ

次回からは簡単です：

```bash
git add .
git commit -m "Your changes"
git push origin main
```

これだけで自動デプロイが実行されます！
