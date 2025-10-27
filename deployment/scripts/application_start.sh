#!/bin/bash
set -e

echo "====== Application Start ======"

cd /home/ec2-user/clean-todo

# 環境変数の設定
# リージョンを取得（EC2メタデータから取得、フォールバックとしてap-northeast-1を使用）
TOKEN=$(curl -X PUT "http://169.254.169.254/latest/api/token" -H "X-aws-ec2-metadata-token-ttl-seconds: 21600" 2>/dev/null)
if [ -n "$TOKEN" ]; then
    AWS_REGION=$(curl -H "X-aws-ec2-metadata-token: $TOKEN" -s http://169.254.169.254/latest/meta-data/placement/region 2>/dev/null)
fi

# リージョンが取得できなかった場合はデフォルト値を使用
if [ -z "$AWS_REGION" ]; then
    AWS_REGION="ap-northeast-1"
    echo "Using default region: ${AWS_REGION}"
else
    echo "Detected region: ${AWS_REGION}"
fi

AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
ECR_REGISTRY="${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com"
IMAGE_TAG=${IMAGE_TAG:-latest}
ECR_REPOSITORY=${ECR_REPOSITORY:-clean-todo}

echo "Using image: ${ECR_REGISTRY}/${ECR_REPOSITORY}:${IMAGE_TAG}"

# Dockerネットワークを作成（存在しない場合）
if [ ! "$(docker network ls -q -f name=clean-todo-network)" ]; then
    echo "Creating Docker network..."
    docker network create clean-todo-network
fi

# データベースコンテナが起動していない場合は起動
if [ ! "$(docker ps -q -f name=clean-todo-db)" ]; then
    echo "Starting database container..."

    # 既存の停止したコンテナを削除
    docker rm -f clean-todo-db 2>/dev/null || true

    docker run -d \
        --name clean-todo-db \
        --network clean-todo-network \
        -e MYSQL_DATABASE=${DB_DATABASE:-laravel} \
        -e MYSQL_USER=${DB_USERNAME:-laravel} \
        -e MYSQL_PASSWORD=${DB_PASSWORD:-secret} \
        -e MYSQL_ROOT_PASSWORD=${DB_ROOT_PASSWORD:-rootsecret} \
        -p 3306:3306 \
        -v clean-todo-db-data:/var/lib/mysql \
        mysql:8.0

    # データベースの起動を待つ
    echo "Waiting for database to be ready..."
    sleep 30
else
    echo "Database container already running"
fi

# アプリケーションコンテナを起動
echo "Starting application container..."

# 既存の停止したコンテナを削除
docker rm -f clean-todo-app 2>/dev/null || true

docker run -d \
    --name clean-todo-app \
    --network clean-todo-network \
    --env-file /home/ec2-user/.env.production \
    -p 80:80 \
    ${ECR_REGISTRY}/${ECR_REPOSITORY}:${IMAGE_TAG}

echo "Application container started: $(docker ps -f name=clean-todo-app --format '{{.ID}}')"

# マイグレーションの実行
echo "Running database migrations..."
docker exec clean-todo-app php artisan migrate --force

echo "Application start completed successfully"
