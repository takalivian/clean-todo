# AWS セットアップコマンド集

このドキュメントでは、デプロイメントに必要なAWSリソースを作成するコマンドを順番に記載しています。

## 前提条件

- AWS CLIがインストールされていること
- AWS認証情報が設定されていること（`aws configure`実行済み）
- 適切な権限を持つAWSアカウント

## 環境変数の設定

まず、以下の環境変数を設定します（適宜変更してください）：

```bash
export AWS_REGION=ap-northeast-1
export AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
export PROJECT_NAME=clean-todo
export S3_BUCKET=${PROJECT_NAME}-deployment-${AWS_ACCOUNT_ID}
export EC2_KEY_NAME=${PROJECT_NAME}-key
export EC2_INSTANCE_TYPE=t3.micro
```

## 1. ECRリポジトリの作成

```bash
# ECRリポジトリを作成
aws ecr create-repository \
  --repository-name ${PROJECT_NAME} \
  --region ${AWS_REGION} \
  --image-scanning-configuration scanOnPush=true

# 作成されたリポジトリURIを確認
aws ecr describe-repositories \
  --repository-names ${PROJECT_NAME} \
  --region ${AWS_REGION} \
  --query 'repositories[0].repositoryUri' \
  --output text
```

## 2. S3バケットの作成

```bash
# S3バケットを作成
aws s3 mb s3://${S3_BUCKET} --region ${AWS_REGION}

# バケットのバージョニングを有効化（オプション）
aws s3api put-bucket-versioning \
  --bucket ${S3_BUCKET} \
  --versioning-configuration Status=Enabled

# バケットが作成されたことを確認
aws s3 ls | grep ${PROJECT_NAME}
```

## 3. IAMロールの作成

### 3.1 EC2用IAMロール

```bash
# EC2用のIAMロールを作成
cat > ec2-trust-policy.json << 'EOF'
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
EOF

aws iam create-role \
  --role-name ${PROJECT_NAME}-EC2-Role \
  --assume-role-policy-document file://ec2-trust-policy.json

# EC2用のポリシーを作成
cat > ec2-policy.json << EOF
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
        "arn:aws:s3:::${S3_BUCKET}/*",
        "arn:aws:s3:::${S3_BUCKET}"
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
EOF

aws iam put-role-policy \
  --role-name ${PROJECT_NAME}-EC2-Role \
  --policy-name ${PROJECT_NAME}-EC2-Policy \
  --policy-document file://ec2-policy.json

# インスタンスプロファイルを作成
aws iam create-instance-profile \
  --instance-profile-name ${PROJECT_NAME}-EC2-InstanceProfile

# ロールをインスタンスプロファイルに追加
aws iam add-role-to-instance-profile \
  --instance-profile-name ${PROJECT_NAME}-EC2-InstanceProfile \
  --role-name ${PROJECT_NAME}-EC2-Role

# 一時ファイルを削除
rm ec2-trust-policy.json ec2-policy.json
```

### 3.2 CodeDeploy用IAMロール

```bash
# CodeDeploy用のIAMロールを作成
cat > codedeploy-trust-policy.json << 'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Service": "codedeploy.amazonaws.com"
      },
      "Action": "sts:AssumeRole"
    }
  ]
}
EOF

aws iam create-role \
  --role-name ${PROJECT_NAME}-CodeDeploy-Role \
  --assume-role-policy-document file://codedeploy-trust-policy.json

# AWS管理ポリシーをアタッチ
aws iam attach-role-policy \
  --role-name ${PROJECT_NAME}-CodeDeploy-Role \
  --policy-arn arn:aws:iam::aws:policy/AWSCodeDeployRole

# 一時ファイルを削除
rm codedeploy-trust-policy.json

# CodeDeployロールのARNを取得（後で使用）
CODEDEPLOY_ROLE_ARN=$(aws iam get-role \
  --role-name ${PROJECT_NAME}-CodeDeploy-Role \
  --query 'Role.Arn' \
  --output text)

echo "CodeDeploy Role ARN: ${CODEDEPLOY_ROLE_ARN}"
```

## 4. セキュリティグループの作成

```bash
# デフォルトVPCのIDを取得
VPC_ID=$(aws ec2 describe-vpcs \
  --filters "Name=isDefault,Values=true" \
  --query 'Vpcs[0].VpcId' \
  --output text)

# セキュリティグループを作成
SECURITY_GROUP_ID=$(aws ec2 create-security-group \
  --group-name ${PROJECT_NAME}-sg \
  --description "Security group for ${PROJECT_NAME}" \
  --vpc-id ${VPC_ID} \
  --query 'GroupId' \
  --output text)

echo "Security Group ID: ${SECURITY_GROUP_ID}"

# HTTPアクセスを許可（ポート80）
aws ec2 authorize-security-group-ingress \
  --group-id ${SECURITY_GROUP_ID} \
  --protocol tcp \
  --port 80 \
  --cidr 0.0.0.0/0

# HTTPSアクセスを許可（ポート443）- オプション
aws ec2 authorize-security-group-ingress \
  --group-id ${SECURITY_GROUP_ID} \
  --protocol tcp \
  --port 443 \
  --cidr 0.0.0.0/0

# SSHアクセスを許可（ポート22）- 自分のIPアドレスのみ推奨
# MY_IP=$(curl -s https://checkip.amazonaws.com)
# aws ec2 authorize-security-group-ingress \
#   --group-id ${SECURITY_GROUP_ID} \
#   --protocol tcp \
#   --port 22 \
#   --cidr ${MY_IP}/32

# 全てからのSSHを許可（開発環境のみ）
aws ec2 authorize-security-group-ingress \
  --group-id ${SECURITY_GROUP_ID} \
  --protocol tcp \
  --port 22 \
  --cidr 0.0.0.0/0
```

## 5. EC2キーペアの作成

```bash
# キーペアを作成（既に持っている場合はスキップ）
aws ec2 create-key-pair \
  --key-name ${EC2_KEY_NAME} \
  --query 'KeyMaterial' \
  --output text > ${EC2_KEY_NAME}.pem

# キーファイルのパーミッションを設定
chmod 400 ${EC2_KEY_NAME}.pem

echo "Key pair created: ${EC2_KEY_NAME}.pem"
echo "IMPORTANT: Keep this file safe! You'll need it to SSH into your EC2 instance."
```

## 6. EC2インスタンスの起動

```bash
# 最新のAmazon Linux 2023 AMIを取得
AMI_ID=$(aws ec2 describe-images \
  --owners amazon \
  --filters "Name=name,Values=al2023-ami-2023.*-x86_64" \
            "Name=state,Values=available" \
  --query 'sort_by(Images, &CreationDate)[-1].ImageId' \
  --output text)

echo "Using AMI: ${AMI_ID}"

# EC2インスタンスを起動
INSTANCE_ID=$(aws ec2 run-instances \
  --image-id ${AMI_ID} \
  --instance-type ${EC2_INSTANCE_TYPE} \
  --key-name ${EC2_KEY_NAME} \
  --security-group-ids ${SECURITY_GROUP_ID} \
  --iam-instance-profile Name=${PROJECT_NAME}-EC2-InstanceProfile \
  --tag-specifications "ResourceType=instance,Tags=[{Key=Name,Value=${PROJECT_NAME}-production}]" \
  --query 'Instances[0].InstanceId' \
  --output text)

echo "EC2 Instance ID: ${INSTANCE_ID}"

# インスタンスが起動するまで待機
echo "Waiting for instance to start..."
aws ec2 wait instance-running --instance-ids ${INSTANCE_ID}

# パブリックIPアドレスを取得
PUBLIC_IP=$(aws ec2 describe-instances \
  --instance-ids ${INSTANCE_ID} \
  --query 'Reservations[0].Instances[0].PublicIpAddress' \
  --output text)

echo "Instance is running!"
echo "Public IP: ${PUBLIC_IP}"
echo "SSH command: ssh -i ${EC2_KEY_NAME}.pem ec2-user@${PUBLIC_IP}"
```

## 7. CodeDeployアプリケーションの作成

```bash
# CodeDeployアプリケーションを作成
aws deploy create-application \
  --application-name ${PROJECT_NAME}-app \
  --compute-platform Server

echo "CodeDeploy application created: ${PROJECT_NAME}-app"
```

## 8. CodeDeployデプロイメントグループの作成

```bash
# デプロイメントグループを作成
aws deploy create-deployment-group \
  --application-name ${PROJECT_NAME}-app \
  --deployment-group-name ${PROJECT_NAME}-deployment-group \
  --service-role-arn ${CODEDEPLOY_ROLE_ARN} \
  --ec2-tag-filters Key=Name,Value=${PROJECT_NAME}-production,Type=KEY_AND_VALUE \
  --deployment-config-name CodeDeployDefault.OneAtATime

echo "Deployment group created: ${PROJECT_NAME}-deployment-group"
```

## 9. セットアップ情報のサマリー

全てのコマンドが成功したら、以下の情報を確認してください：

```bash
echo "================================"
echo "Setup Summary"
echo "================================"
echo "AWS Region: ${AWS_REGION}"
echo "AWS Account ID: ${AWS_ACCOUNT_ID}"
echo "ECR Repository: ${PROJECT_NAME}"
echo "S3 Bucket: ${S3_BUCKET}"
echo "EC2 Instance ID: ${INSTANCE_ID}"
echo "EC2 Public IP: ${PUBLIC_IP}"
echo "SSH Key: ${EC2_KEY_NAME}.pem"
echo "Security Group ID: ${SECURITY_GROUP_ID}"
echo "CodeDeploy Application: ${PROJECT_NAME}-app"
echo "CodeDeploy Deployment Group: ${PROJECT_NAME}-deployment-group"
echo "================================"
echo ""
echo "Next steps:"
echo "1. SSH into EC2: ssh -i ${EC2_KEY_NAME}.pem ec2-user@${PUBLIC_IP}"
echo "2. Follow EC2_SETUP.md to install Docker, AWS CLI, and CodeDeploy Agent"
echo "3. Create /home/ec2-user/.env.production file"
echo "4. Push to main branch to trigger deployment"
echo "================================"
```

## 10. GitHub Secretsに追加する値

GitHub Secretsに以下を追加済みであることを確認：

```bash
echo "GitHub Secrets to set:"
echo "- AWS_ACCESS_KEY_ID: (your AWS access key)"
echo "- AWS_SECRET_ACCESS_KEY: (your AWS secret key)"
echo "- S3_BUCKET: ${S3_BUCKET}"
```

## トラブルシューティング

### リソースが既に存在するエラー

リソースが既に存在する場合は、既存のものを使用するか削除してから再作成してください。

### IAMロールの作成に時間がかかる

IAMロールの作成後、すぐにEC2インスタンスに適用できない場合があります。
数分待ってから再試行してください。

### EC2インスタンスに接続できない

- セキュリティグループでSSH（ポート22）が開放されているか確認
- キーファイルのパーミッションが400になっているか確認（`chmod 400 *.pem`）
- インスタンスが起動しているか確認（`aws ec2 describe-instances`）

## クリーンアップ（削除）コマンド

全てのリソースを削除する場合：

```bash
# EC2インスタンスを終了
aws ec2 terminate-instances --instance-ids ${INSTANCE_ID}
aws ec2 wait instance-terminated --instance-ids ${INSTANCE_ID}

# セキュリティグループを削除
aws ec2 delete-security-group --group-id ${SECURITY_GROUP_ID}

# キーペアを削除
aws ec2 delete-key-pair --key-name ${EC2_KEY_NAME}
rm ${EC2_KEY_NAME}.pem

# CodeDeployデプロイメントグループを削除
aws deploy delete-deployment-group \
  --application-name ${PROJECT_NAME}-app \
  --deployment-group-name ${PROJECT_NAME}-deployment-group

# CodeDeployアプリケーションを削除
aws deploy delete-application --application-name ${PROJECT_NAME}-app

# S3バケットを削除（バケットが空であること）
aws s3 rb s3://${S3_BUCKET} --force

# ECRリポジトリを削除
aws ecr delete-repository \
  --repository-name ${PROJECT_NAME} \
  --force

# IAMリソースを削除
aws iam remove-role-from-instance-profile \
  --instance-profile-name ${PROJECT_NAME}-EC2-InstanceProfile \
  --role-name ${PROJECT_NAME}-EC2-Role

aws iam delete-instance-profile \
  --instance-profile-name ${PROJECT_NAME}-EC2-InstanceProfile

aws iam delete-role-policy \
  --role-name ${PROJECT_NAME}-EC2-Role \
  --policy-name ${PROJECT_NAME}-EC2-Policy

aws iam delete-role --role-name ${PROJECT_NAME}-EC2-Role

aws iam detach-role-policy \
  --role-name ${PROJECT_NAME}-CodeDeploy-Role \
  --policy-arn arn:aws:iam::aws:policy/AWSCodeDeployRole

aws iam delete-role --role-name ${PROJECT_NAME}-CodeDeploy-Role
```
