#!/bin/bash
set -e

echo "====== Application Start ======"

cd /home/ec2-user/clean-todo

# 環境変数の設定
AWS_REGION=$(aws configure get region)
AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
ECR_REGISTRY="${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com"
IMAGE_TAG=${IMAGE_TAG:-latest}
ECR_REPOSITORY=${ECR_REPOSITORY:-clean-todo}

# データベースコンテナが起動していない場合は起動
if [ ! "$(docker ps -q -f name=clean-todo-db)" ]; then
    echo "Starting database container..."
    docker run -d \
        --name clean-todo-db \
        --network clean-todo-network 2>/dev/null || docker network create clean-todo-network

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
fi

# アプリケーションコンテナを起動
echo "Starting application container..."
docker run -d \
    --name clean-todo-app \
    --network clean-todo-network \
    --env-file /home/ec2-user/.env.production \
    -p 80:80 \
    ${ECR_REGISTRY}/${ECR_REPOSITORY}:${IMAGE_TAG}

# マイグレーションの実行
echo "Running database migrations..."
docker exec clean-todo-app php artisan migrate --force

echo "Application start completed successfully"
