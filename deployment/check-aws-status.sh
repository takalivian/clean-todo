#!/bin/bash
# AWSリソースの状態を確認するスクリプト

echo "================================"
echo "AWS Resources Status Check"
echo "================================"
echo ""

export AWS_REGION=ap-northeast-1
export PROJECT_NAME=clean-todo

# AWS認証確認
echo "1. AWS Authentication:"
if aws sts get-caller-identity > /dev/null 2>&1; then
    AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
    echo "✅ Connected to AWS Account: ${AWS_ACCOUNT_ID}"
else
    echo "❌ Not authenticated to AWS"
    exit 1
fi
echo ""

# ECRリポジトリ
echo "2. ECR Repository:"
if aws ecr describe-repositories --repository-names ${PROJECT_NAME} --region ${AWS_REGION} > /dev/null 2>&1; then
    echo "✅ ECR repository '${PROJECT_NAME}' exists"
else
    echo "❌ ECR repository '${PROJECT_NAME}' NOT found"
fi
echo ""

# S3バケット
echo "3. S3 Buckets:"
aws s3 ls | grep ${PROJECT_NAME} || echo "❌ No S3 buckets found with '${PROJECT_NAME}'"
echo ""

# EC2インスタンス
echo "4. EC2 Instances:"
INSTANCE_DATA=$(aws ec2 describe-instances \
    --filters "Name=tag:Name,Values=${PROJECT_NAME}-production" \
    --query 'Reservations[*].Instances[*].[InstanceId,State.Name,PublicIpAddress,Tags[?Key==`Name`].Value|[0]]' \
    --output text \
    --region ${AWS_REGION})

if [ -z "$INSTANCE_DATA" ]; then
    echo "❌ No EC2 instance found with tag Name=${PROJECT_NAME}-production"
else
    echo "✅ EC2 Instance found:"
    echo "$INSTANCE_DATA" | while read line; do
        echo "   $line"
    done
    INSTANCE_ID=$(echo "$INSTANCE_DATA" | awk '{print $1}')
fi
echo ""

# IAMロール
echo "5. IAM Roles:"
if aws iam get-role --role-name ${PROJECT_NAME}-EC2-Role > /dev/null 2>&1; then
    echo "✅ IAM Role '${PROJECT_NAME}-EC2-Role' exists"
else
    echo "❌ IAM Role '${PROJECT_NAME}-EC2-Role' NOT found"
fi

if aws iam get-role --role-name ${PROJECT_NAME}-CodeDeploy-Role > /dev/null 2>&1; then
    echo "✅ IAM Role '${PROJECT_NAME}-CodeDeploy-Role' exists"
else
    echo "❌ IAM Role '${PROJECT_NAME}-CodeDeploy-Role' NOT found"
fi
echo ""

# CodeDeployアプリケーション
echo "6. CodeDeploy Application:"
if aws deploy get-application --application-name ${PROJECT_NAME}-app --region ${AWS_REGION} > /dev/null 2>&1; then
    echo "✅ CodeDeploy application '${PROJECT_NAME}-app' exists"
else
    echo "❌ CodeDeploy application '${PROJECT_NAME}-app' NOT found"
fi
echo ""

# CodeDeployデプロイメントグループ
echo "7. CodeDeploy Deployment Group:"
if aws deploy get-deployment-group \
    --application-name ${PROJECT_NAME}-app \
    --deployment-group-name ${PROJECT_NAME}-deployment-group \
    --region ${AWS_REGION} > /dev/null 2>&1; then
    echo "✅ Deployment group '${PROJECT_NAME}-deployment-group' exists"

    # デプロイメントグループの設定を表示
    echo ""
    echo "   Deployment Group Configuration:"
    aws deploy get-deployment-group \
        --application-name ${PROJECT_NAME}-app \
        --deployment-group-name ${PROJECT_NAME}-deployment-group \
        --region ${AWS_REGION} \
        --query 'deploymentGroupInfo.{ServiceRole:serviceRoleArn,EC2TagFilters:ec2TagFilters}' \
        --output json
else
    echo "❌ Deployment group '${PROJECT_NAME}-deployment-group' NOT found"
fi
echo ""

echo "================================"
echo "Summary"
echo "================================"
echo ""

# 問題のあるリソースをまとめる
MISSING_RESOURCES=""

if ! aws ecr describe-repositories --repository-names ${PROJECT_NAME} --region ${AWS_REGION} > /dev/null 2>&1; then
    MISSING_RESOURCES="${MISSING_RESOURCES}- ECR repository\n"
fi

if [ -z "$INSTANCE_DATA" ]; then
    MISSING_RESOURCES="${MISSING_RESOURCES}- EC2 instance\n"
fi

if ! aws iam get-role --role-name ${PROJECT_NAME}-CodeDeploy-Role > /dev/null 2>&1; then
    MISSING_RESOURCES="${MISSING_RESOURCES}- CodeDeploy IAM role\n"
fi

if ! aws deploy get-application --application-name ${PROJECT_NAME}-app --region ${AWS_REGION} > /dev/null 2>&1; then
    MISSING_RESOURCES="${MISSING_RESOURCES}- CodeDeploy application\n"
fi

if ! aws deploy get-deployment-group --application-name ${PROJECT_NAME}-app --deployment-group-name ${PROJECT_NAME}-deployment-group --region ${AWS_REGION} > /dev/null 2>&1; then
    MISSING_RESOURCES="${MISSING_RESOURCES}- CodeDeploy deployment group\n"
fi

if [ -z "$MISSING_RESOURCES" ]; then
    echo "✅ All resources are configured correctly!"
    echo ""
    echo "You can now trigger a deployment."
else
    echo "❌ Missing resources:"
    echo -e "$MISSING_RESOURCES"
    echo ""
    echo "To fix:"
    echo "  Run: ./deployment/create-aws-resources.sh"
    echo "  Or: ./deployment/fix-codedeploy.sh (for CodeDeploy only)"
fi
echo ""
