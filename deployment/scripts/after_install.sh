#!/bin/bash
set -e

echo "====== After Install ======"

cd /home/ec2-user/clean-todo

# AWS CLIとDockerがインストールされているか確認
if ! command -v aws &> /dev/null; then
    echo "ERROR: AWS CLI is not installed"
    exit 1
fi

if ! command -v docker &> /dev/null; then
    echo "ERROR: Docker is not installed"
    exit 1
fi

# ECRにログイン
echo "Logging in to ECR..."

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

aws ecr get-login-password --region ${AWS_REGION} | docker login --username AWS --password-stdin ${ECR_REGISTRY}

# 環境変数から最新のイメージタグを取得（GitHub Actionsから渡される）
IMAGE_TAG=${IMAGE_TAG:-latest}
ECR_REPOSITORY=${ECR_REPOSITORY:-clean-todo}

# Dockerイメージをpull
echo "Pulling Docker image: ${ECR_REGISTRY}/${ECR_REPOSITORY}:${IMAGE_TAG}"
docker pull ${ECR_REGISTRY}/${ECR_REPOSITORY}:${IMAGE_TAG}

# .envファイルの確認（事前にEC2に配置されている想定）
if [ ! -f /home/ec2-user/.env.production ]; then
    echo "WARNING: /home/ec2-user/.env.production not found"
    echo "Please create this file with your production environment variables"
fi

echo "After install completed successfully"
