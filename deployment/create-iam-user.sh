#!/bin/bash
# clean-todoプロジェクト専用のIAMユーザーを作成するスクリプト

set -e

echo "================================"
echo "Create IAM User for Clean Todo"
echo "================================"
echo ""

export IAM_USER_NAME=clean-todo-deploy
export AWS_REGION=ap-northeast-1
export PROJECT_NAME=clean-todo

# AWS認証確認
if ! aws sts get-caller-identity > /dev/null 2>&1; then
    echo "❌ ERROR: AWS credentials are not configured"
    exit 1
fi

AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
echo "AWS Account ID: ${AWS_ACCOUNT_ID}"
echo ""

# IAMユーザーの作成
echo "1. Creating IAM user '${IAM_USER_NAME}'..."
if aws iam get-user --user-name ${IAM_USER_NAME} > /dev/null 2>&1; then
    echo "⚠️  IAM user '${IAM_USER_NAME}' already exists"
else
    aws iam create-user --user-name ${IAM_USER_NAME}
    echo "✅ IAM user created"
fi
echo ""

# ポリシーの作成
echo "2. Creating IAM policy..."
POLICY_NAME="${PROJECT_NAME}-deploy-policy"

cat > /tmp/deploy-policy.json << EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "ecr:GetAuthorizationToken",
        "ecr:BatchCheckLayerAvailability",
        "ecr:GetDownloadUrlForLayer",
        "ecr:BatchGetImage",
        "ecr:PutImage",
        "ecr:InitiateLayerUpload",
        "ecr:UploadLayerPart",
        "ecr:CompleteLayerUpload"
      ],
      "Resource": "*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:ListBucket"
      ],
      "Resource": [
        "arn:aws:s3:::${PROJECT_NAME}-deployment-${AWS_ACCOUNT_ID}/*",
        "arn:aws:s3:::${PROJECT_NAME}-deployment-${AWS_ACCOUNT_ID}"
      ]
    },
    {
      "Effect": "Allow",
      "Action": [
        "codedeploy:CreateDeployment",
        "codedeploy:GetDeployment",
        "codedeploy:GetDeploymentConfig",
        "codedeploy:GetApplicationRevision",
        "codedeploy:RegisterApplicationRevision"
      ],
      "Resource": "*"
    }
  ]
}
EOF

POLICY_ARN="arn:aws:iam::${AWS_ACCOUNT_ID}:policy/${POLICY_NAME}"

if aws iam get-policy --policy-arn ${POLICY_ARN} > /dev/null 2>&1; then
    echo "⚠️  Policy '${POLICY_NAME}' already exists"
else
    aws iam create-policy \
        --policy-name ${POLICY_NAME} \
        --policy-document file:///tmp/deploy-policy.json > /dev/null
    echo "✅ Policy created"
fi

rm /tmp/deploy-policy.json
echo ""

# ポリシーをユーザーにアタッチ
echo "3. Attaching policy to user..."
if aws iam list-attached-user-policies --user-name ${IAM_USER_NAME} | grep -q ${POLICY_NAME}; then
    echo "⚠️  Policy already attached"
else
    aws iam attach-user-policy \
        --user-name ${IAM_USER_NAME} \
        --policy-arn ${POLICY_ARN}
    echo "✅ Policy attached"
fi
echo ""

# アクセスキーの作成
echo "4. Creating access key..."
echo "⚠️  WARNING: Access keys will be displayed only once!"
echo ""

EXISTING_KEYS=$(aws iam list-access-keys --user-name ${IAM_USER_NAME} --query 'AccessKeyMetadata[].AccessKeyId' --output text)

if [ -n "$EXISTING_KEYS" ]; then
    echo "⚠️  Access key(s) already exist for this user:"
    echo "$EXISTING_KEYS"
    echo ""
    read -p "Do you want to create a new access key? (yes/no): " -r
    if [[ ! $REPLY =~ ^yes$ ]]; then
        echo "Skipping access key creation"
        echo ""
        echo "To use existing keys, retrieve them from AWS console or your secure storage"
        echo "Then update GitHub Secrets:"
        echo "  AWS_ACCESS_KEY_ID: (your existing key)"
        echo "  AWS_SECRET_ACCESS_KEY: (your existing secret)"
        exit 0
    fi
fi

CREDENTIALS=$(aws iam create-access-key --user-name ${IAM_USER_NAME})

ACCESS_KEY_ID=$(echo $CREDENTIALS | jq -r '.AccessKey.AccessKeyId')
SECRET_ACCESS_KEY=$(echo $CREDENTIALS | jq -r '.AccessKey.SecretAccessKey')

echo "================================"
echo "✅ IAM User Setup Complete!"
echo "================================"
echo ""
echo "IAM User: ${IAM_USER_NAME}"
echo "Account: ${AWS_ACCOUNT_ID}"
echo ""
echo "⚠️  IMPORTANT: Save these credentials securely!"
echo "They will not be shown again."
echo ""
echo "AWS_ACCESS_KEY_ID:"
echo "${ACCESS_KEY_ID}"
echo ""
echo "AWS_SECRET_ACCESS_KEY:"
echo "${SECRET_ACCESS_KEY}"
echo ""
echo "================================"
echo "Next steps:"
echo "================================"
echo ""
echo "1. Update GitHub Secrets:"
echo "   - Go to: Settings → Secrets and variables → Actions"
echo "   - Update AWS_ACCESS_KEY_ID: ${ACCESS_KEY_ID}"
echo "   - Update AWS_SECRET_ACCESS_KEY: ${SECRET_ACCESS_KEY}"
echo "   - Verify S3_BUCKET: clean-todo-deployment-${AWS_ACCOUNT_ID}"
echo ""
echo "2. Optionally, update your local AWS CLI config:"
echo "   aws configure --profile clean-todo"
echo "   AWS Access Key ID: ${ACCESS_KEY_ID}"
echo "   AWS Secret Access Key: ${SECRET_ACCESS_KEY}"
echo "   Default region: ap-northeast-1"
echo ""
echo "3. Test the new credentials:"
echo "   aws sts get-caller-identity --profile clean-todo"
echo ""
echo "================================"

# 認証情報をファイルに保存（オプション）
echo ""
read -p "Save credentials to file? (yes/no): " -r
if [[ $REPLY =~ ^yes$ ]]; then
    cat > clean-todo-credentials.txt << EOF
AWS_ACCESS_KEY_ID=${ACCESS_KEY_ID}
AWS_SECRET_ACCESS_KEY=${SECRET_ACCESS_KEY}
S3_BUCKET=clean-todo-deployment-${AWS_ACCOUNT_ID}
EOF
    chmod 600 clean-todo-credentials.txt
    echo "✅ Credentials saved to: clean-todo-credentials.txt"
    echo "⚠️  Keep this file secure and do NOT commit it to git!"
fi
