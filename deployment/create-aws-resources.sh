#!/bin/bash
# AWSリソースを一括作成するスクリプト

set -e

echo "================================"
echo "AWS Resources Setup Script"
echo "================================"
echo ""

# 環境変数を設定
export AWS_REGION=ap-northeast-1
export PROJECT_NAME=clean-todo
export EC2_KEY_NAME=${PROJECT_NAME}-keypair
export EC2_INSTANCE_TYPE=t3.micro

# AWS認証情報を事前に取得してS3バケット名を生成
AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
export S3_BUCKET=${PROJECT_NAME}-deployment-${AWS_ACCOUNT_ID}

echo "Configuration:"
echo "  Region: ${AWS_REGION}"
echo "  Project: ${PROJECT_NAME}"
echo "  S3 Bucket: ${S3_BUCKET}"
echo "  Key Name: ${EC2_KEY_NAME}"
echo ""

# AWS認証情報の確認
echo "Checking AWS credentials..."
if ! aws sts get-caller-identity > /dev/null 2>&1; then
    echo "❌ ERROR: AWS credentials are not configured properly"
    echo "Please run: aws configure"
    exit 1
fi

echo "✅ AWS Account ID: ${AWS_ACCOUNT_ID}"
echo ""

# VPC IDを取得
echo "Getting VPC ID..."
VPC_ID=$(aws ec2 describe-vpcs --filters "Name=isDefault,Values=true" --query 'Vpcs[0].VpcId' --output text --region ${AWS_REGION})
if [ -z "$VPC_ID" ] || [ "$VPC_ID" = "None" ]; then
    echo "❌ ERROR: Default VPC not found"
    exit 1
fi
echo "✅ VPC ID: ${VPC_ID}"
echo ""

# 1. ECRリポジトリを作成
echo "1. Creating ECR repository..."
if aws ecr describe-repositories --repository-names ${PROJECT_NAME} --region ${AWS_REGION} > /dev/null 2>&1; then
    echo "⚠️  ECR repository already exists"
else
    aws ecr create-repository \
        --repository-name ${PROJECT_NAME} \
        --region ${AWS_REGION} \
        --image-scanning-configuration scanOnPush=true > /dev/null
    echo "✅ ECR repository created"
fi
echo ""

# 2. S3バケットを作成
echo "2. Creating S3 bucket..."
if aws s3 ls s3://${S3_BUCKET} > /dev/null 2>&1; then
    echo "⚠️  S3 bucket already exists"
else
    aws s3 mb s3://${S3_BUCKET} --region ${AWS_REGION}
    echo "✅ S3 bucket created"
fi
echo ""

# 3. EC2用IAMロールを作成
echo "3. Creating EC2 IAM role..."
if aws iam get-role --role-name ${PROJECT_NAME}-EC2-Role > /dev/null 2>&1; then
    echo "⚠️  EC2 IAM role already exists"
else
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
        --assume-role-policy-document file:///tmp/ec2-trust-policy.json > /dev/null

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

    echo "✅ EC2 IAM role created"
fi

# インスタンスプロファイルを作成
if aws iam get-instance-profile --instance-profile-name ${PROJECT_NAME}-EC2-InstanceProfile > /dev/null 2>&1; then
    echo "⚠️  Instance profile already exists"
else
    aws iam create-instance-profile --instance-profile-name ${PROJECT_NAME}-EC2-InstanceProfile > /dev/null
    aws iam add-role-to-instance-profile \
        --instance-profile-name ${PROJECT_NAME}-EC2-InstanceProfile \
        --role-name ${PROJECT_NAME}-EC2-Role > /dev/null
    echo "✅ Instance profile created"
fi
echo ""

# IAMロールの伝播を待つ
echo "Waiting for IAM role to propagate..."
sleep 10
echo ""

# 4. CodeDeploy用IAMロールを作成
echo "4. Creating CodeDeploy IAM role..."
if aws iam get-role --role-name ${PROJECT_NAME}-CodeDeploy-Role > /dev/null 2>&1; then
    echo "⚠️  CodeDeploy IAM role already exists"
else
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
        --assume-role-policy-document file:///tmp/codedeploy-trust-policy.json > /dev/null

    aws iam attach-role-policy \
        --role-name ${PROJECT_NAME}-CodeDeploy-Role \
        --policy-arn arn:aws:iam::aws:policy/AWSCodeDeployRole

    echo "✅ CodeDeploy IAM role created"
fi

CODEDEPLOY_ROLE_ARN=$(aws iam get-role --role-name ${PROJECT_NAME}-CodeDeploy-Role --query 'Role.Arn' --output text)
echo "CodeDeploy Role ARN: ${CODEDEPLOY_ROLE_ARN}"
echo ""

# 5. セキュリティグループを作成
echo "5. Creating security group..."
EXISTING_SG=$(aws ec2 describe-security-groups --filters "Name=group-name,Values=${PROJECT_NAME}-sg" --query 'SecurityGroups[0].GroupId' --output text --region ${AWS_REGION} 2>/dev/null)

if [ "$EXISTING_SG" != "None" ] && [ -n "$EXISTING_SG" ]; then
    echo "⚠️  Security group already exists"
    SECURITY_GROUP_ID=$EXISTING_SG
else
    SECURITY_GROUP_ID=$(aws ec2 create-security-group \
        --group-name ${PROJECT_NAME}-sg \
        --description "Security group for ${PROJECT_NAME}" \
        --vpc-id ${VPC_ID} \
        --region ${AWS_REGION} \
        --query 'GroupId' --output text)

    # ポート80を開放
    aws ec2 authorize-security-group-ingress \
        --group-id ${SECURITY_GROUP_ID} \
        --protocol tcp --port 80 --cidr 0.0.0.0/0 \
        --region ${AWS_REGION} > /dev/null 2>&1 || true

    # ポート443を開放
    aws ec2 authorize-security-group-ingress \
        --group-id ${SECURITY_GROUP_ID} \
        --protocol tcp --port 443 --cidr 0.0.0.0/0 \
        --region ${AWS_REGION} > /dev/null 2>&1 || true

    # ポート22を開放
    aws ec2 authorize-security-group-ingress \
        --group-id ${SECURITY_GROUP_ID} \
        --protocol tcp --port 22 --cidr 0.0.0.0/0 \
        --region ${AWS_REGION} > /dev/null 2>&1 || true

    echo "✅ Security group created"
fi
echo "Security Group ID: ${SECURITY_GROUP_ID}"
echo ""

# 6. EC2キーペアを作成
echo "6. Creating EC2 key pair..."
if [ -f "${EC2_KEY_NAME}.pem" ]; then
    echo "⚠️  Key file already exists: ${EC2_KEY_NAME}.pem"
elif aws ec2 describe-key-pairs --key-names ${EC2_KEY_NAME} --region ${AWS_REGION} > /dev/null 2>&1; then
    echo "⚠️  Key pair already exists in AWS (but local file not found)"
    echo "⚠️  Please download or create a new key pair manually"
else
    aws ec2 create-key-pair \
        --key-name ${EC2_KEY_NAME} \
        --query 'KeyMaterial' \
        --output text \
        --region ${AWS_REGION} > ${EC2_KEY_NAME}.pem
    chmod 400 ${EC2_KEY_NAME}.pem
    echo "✅ Key pair created: ${EC2_KEY_NAME}.pem"
fi
echo ""

# 7. EC2インスタンスを起動
echo "7. Launching EC2 instance..."
EXISTING_INSTANCE=$(aws ec2 describe-instances \
    --filters "Name=tag:Name,Values=${PROJECT_NAME}-production" "Name=instance-state-name,Values=running,pending,stopping,stopped" \
    --query 'Reservations[0].Instances[0].InstanceId' \
    --output text \
    --region ${AWS_REGION} 2>/dev/null)

if [ "$EXISTING_INSTANCE" != "None" ] && [ -n "$EXISTING_INSTANCE" ]; then
    echo "⚠️  EC2 instance already exists"
    INSTANCE_ID=$EXISTING_INSTANCE
else
    AMI_ID=$(aws ec2 describe-images \
        --owners amazon \
        --filters "Name=name,Values=al2023-ami-2023.*-x86_64" "Name=state,Values=available" \
        --query 'sort_by(Images, &CreationDate)[-1].ImageId' \
        --output text \
        --region ${AWS_REGION})

    echo "Using AMI: ${AMI_ID}"

    INSTANCE_ID=$(aws ec2 run-instances \
        --image-id ${AMI_ID} \
        --instance-type ${EC2_INSTANCE_TYPE} \
        --key-name ${EC2_KEY_NAME} \
        --security-group-ids ${SECURITY_GROUP_ID} \
        --iam-instance-profile Name=${PROJECT_NAME}-EC2-InstanceProfile \
        --tag-specifications "ResourceType=instance,Tags=[{Key=Name,Value=${PROJECT_NAME}-production}]" \
        --region ${AWS_REGION} \
        --query 'Instances[0].InstanceId' \
        --output text)

    echo "✅ EC2 instance launched"
fi

echo "Instance ID: ${INSTANCE_ID}"
echo "Waiting for instance to start..."
aws ec2 wait instance-running --instance-ids ${INSTANCE_ID} --region ${AWS_REGION}

PUBLIC_IP=$(aws ec2 describe-instances \
    --instance-ids ${INSTANCE_ID} \
    --query 'Reservations[0].Instances[0].PublicIpAddress' \
    --output text \
    --region ${AWS_REGION})

echo "✅ Instance is running"
echo "Public IP: ${PUBLIC_IP}"
echo ""

# 8. CodeDeployアプリケーションを作成
echo "8. Creating CodeDeploy application..."
if aws deploy get-application --application-name ${PROJECT_NAME}-app --region ${AWS_REGION} > /dev/null 2>&1; then
    echo "⚠️  CodeDeploy application already exists"
else
    aws deploy create-application \
        --application-name ${PROJECT_NAME}-app \
        --compute-platform Server \
        --region ${AWS_REGION} > /dev/null
    echo "✅ CodeDeploy application created"
fi
echo ""

# 9. CodeDeployデプロイメントグループを作成
echo "9. Creating CodeDeploy deployment group..."
if aws deploy get-deployment-group \
    --application-name ${PROJECT_NAME}-app \
    --deployment-group-name ${PROJECT_NAME}-deployment-group \
    --region ${AWS_REGION} > /dev/null 2>&1; then
    echo "⚠️  CodeDeploy deployment group already exists"
else
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

# クリーンアップ
rm -f /tmp/ec2-trust-policy.json /tmp/ec2-policy.json /tmp/codedeploy-trust-policy.json

# セットアップ情報を出力
echo "================================"
echo "✅ AWS Setup Complete!"
echo "================================"
echo ""
echo "Summary:"
echo "  AWS Region: ${AWS_REGION}"
echo "  AWS Account: ${AWS_ACCOUNT_ID}"
echo "  ECR Repository: ${PROJECT_NAME}"
echo "  S3 Bucket: ${S3_BUCKET}"
echo "  EC2 Instance ID: ${INSTANCE_ID}"
echo "  EC2 Public IP: ${PUBLIC_IP}"
echo "  SSH Key: ${EC2_KEY_NAME}.pem"
echo "  Security Group: ${SECURITY_GROUP_ID}"
echo "  CodeDeploy App: ${PROJECT_NAME}-app"
echo "  CodeDeploy Group: ${PROJECT_NAME}-deployment-group"
echo ""
echo "⚠️  IMPORTANT: Verify GitHub Secret"
echo "  S3_BUCKET should be set to: ${S3_BUCKET}"
echo ""
echo "Next Steps:"
echo "  1. SSH into EC2:"
echo "     ssh -i ${EC2_KEY_NAME}.pem ec2-user@${PUBLIC_IP}"
echo ""
echo "  2. Follow deployment/EC2_SETUP.md to setup EC2"
echo ""
echo "  3. Create /home/ec2-user/.env.production file"
echo ""
echo "  4. Push to main branch to trigger deployment:"
echo "     git add ."
echo "     git commit -m 'Deploy to production'"
echo "     git push origin main"
echo ""
echo "================================"
