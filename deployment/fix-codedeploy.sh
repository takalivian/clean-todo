#!/bin/bash
# CodeDeployリソースを作成/修正するスクリプト

set -e

echo "================================"
echo "CodeDeploy Resources Fix Script"
echo "================================"
echo ""

export AWS_REGION=ap-northeast-1
export PROJECT_NAME=clean-todo

# AWS認証情報の確認
echo "Checking AWS credentials..."
if ! aws sts get-caller-identity > /dev/null 2>&1; then
    echo "❌ ERROR: AWS credentials are not configured"
    exit 1
fi

AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
echo "✅ AWS Account ID: ${AWS_ACCOUNT_ID}"
echo ""

# 1. EC2インスタンスの確認
echo "1. Checking EC2 instance..."
INSTANCE_ID=$(aws ec2 describe-instances \
    --filters "Name=tag:Name,Values=${PROJECT_NAME}-production" "Name=instance-state-name,Values=running,pending,stopping,stopped" \
    --query 'Reservations[0].Instances[0].InstanceId' \
    --output text \
    --region ${AWS_REGION} 2>/dev/null)

if [ "$INSTANCE_ID" == "None" ] || [ -z "$INSTANCE_ID" ]; then
    echo "❌ ERROR: EC2 instance not found with tag Name=${PROJECT_NAME}-production"
    echo ""
    echo "Please run: ./deployment/create-aws-resources.sh"
    exit 1
fi

echo "✅ EC2 Instance found: ${INSTANCE_ID}"
echo ""

# 2. CodeDeploy用IAMロールの確認/作成
echo "2. Checking CodeDeploy IAM role..."
if aws iam get-role --role-name ${PROJECT_NAME}-CodeDeploy-Role --region ${AWS_REGION} > /dev/null 2>&1; then
    echo "✅ CodeDeploy IAM role exists"
else
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
        --assume-role-policy-document file:///tmp/codedeploy-trust-policy.json \
        --region ${AWS_REGION} > /dev/null

    aws iam attach-role-policy \
        --role-name ${PROJECT_NAME}-CodeDeploy-Role \
        --policy-arn arn:aws:iam::aws:policy/AWSCodeDeployRole \
        --region ${AWS_REGION}

    rm /tmp/codedeploy-trust-policy.json
    echo "✅ CodeDeploy IAM role created"

    echo "Waiting for IAM role to propagate..."
    sleep 15
fi

CODEDEPLOY_ROLE_ARN=$(aws iam get-role --role-name ${PROJECT_NAME}-CodeDeploy-Role --query 'Role.Arn' --output text)
echo "CodeDeploy Role ARN: ${CODEDEPLOY_ROLE_ARN}"
echo ""

# 3. CodeDeployアプリケーションの確認/作成
echo "3. Checking CodeDeploy application..."
if aws deploy get-application --application-name ${PROJECT_NAME}-app --region ${AWS_REGION} > /dev/null 2>&1; then
    echo "✅ CodeDeploy application exists"
else
    echo "Creating CodeDeploy application..."
    aws deploy create-application \
        --application-name ${PROJECT_NAME}-app \
        --compute-platform Server \
        --region ${AWS_REGION} > /dev/null
    echo "✅ CodeDeploy application created"
fi
echo ""

# 4. CodeDeployデプロイメントグループの確認/作成
echo "4. Checking CodeDeploy deployment group..."
if aws deploy get-deployment-group \
    --application-name ${PROJECT_NAME}-app \
    --deployment-group-name ${PROJECT_NAME}-deployment-group \
    --region ${AWS_REGION} > /dev/null 2>&1; then
    echo "✅ CodeDeploy deployment group exists"
else
    echo "Creating CodeDeploy deployment group..."
    aws deploy create-deployment-group \
        --application-name ${PROJECT_NAME}-app \
        --deployment-group-name ${PROJECT_NAME}-deployment-group \
        --service-role-arn ${CODEDEPLOY_ROLE_ARN} \
        --ec2-tag-filters Key=Name,Value=${PROJECT_NAME}-production,Type=KEY_AND_VALUE \
        --deployment-config-name CodeDeployDefault.OneAtATime \
        --region ${AWS_REGION} > /dev/null
    echo "✅ CodeDeploy deployment group created"
fi
echo ""

# 5. 設定の検証
echo "5. Verifying configuration..."

# EC2インスタンスのタグを確認
INSTANCE_NAME=$(aws ec2 describe-instances \
    --instance-ids ${INSTANCE_ID} \
    --query 'Reservations[0].Instances[0].Tags[?Key==`Name`].Value' \
    --output text \
    --region ${AWS_REGION})

echo "EC2 Instance Tag Name: ${INSTANCE_NAME}"

if [ "$INSTANCE_NAME" != "${PROJECT_NAME}-production" ]; then
    echo "⚠️  WARNING: Instance name tag does not match expected value"
    echo "Expected: ${PROJECT_NAME}-production"
    echo "Actual: ${INSTANCE_NAME}"
fi

# デプロイメントグループの詳細を確認
aws deploy get-deployment-group \
    --application-name ${PROJECT_NAME}-app \
    --deployment-group-name ${PROJECT_NAME}-deployment-group \
    --region ${AWS_REGION} \
    --query 'deploymentGroupInfo.ec2TagFilters' \
    --output table

echo ""
echo "================================"
echo "✅ CodeDeploy Setup Complete!"
echo "================================"
echo ""
echo "Summary:"
echo "  CodeDeploy Application: ${PROJECT_NAME}-app"
echo "  Deployment Group: ${PROJECT_NAME}-deployment-group"
echo "  Target EC2 Instance: ${INSTANCE_ID} (${INSTANCE_NAME})"
echo "  Service Role: ${CODEDEPLOY_ROLE_ARN}"
echo ""
echo "Next steps:"
echo "  1. Trigger deployment from GitHub Actions"
echo "  2. Or push a commit to main branch"
echo "  3. Monitor deployment in AWS CodeDeploy console"
echo ""
echo "================================"
