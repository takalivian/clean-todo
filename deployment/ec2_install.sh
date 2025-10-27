#!/bin/bash
# EC2インスタンスで実行するセットアップスクリプト
# 使い方: ssh経由でこのスクリプトをEC2にコピーして実行

set -e

echo "================================"
echo "EC2 Instance Setup Script"
echo "================================"

# システムパッケージの更新
echo "1. Updating system packages..."
sudo yum update -y

# Dockerのインストール
echo "2. Installing Docker..."
sudo yum install -y docker

# Dockerサービスを起動
echo "3. Starting Docker service..."
sudo systemctl start docker
sudo systemctl enable docker

# ec2-userをdockerグループに追加
echo "4. Adding ec2-user to docker group..."
sudo usermod -a -G docker ec2-user

# Docker Composeのインストール
echo "5. Installing Docker Compose..."
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# AWS CLI v2のインストール
echo "6. Installing AWS CLI v2..."
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
unzip awscliv2.zip
sudo ./aws/install
rm -rf aws awscliv2.zip

# CodeDeploy Agentのインストール
echo "7. Installing CodeDeploy Agent..."
sudo yum install -y ruby wget
cd /home/ec2-user
wget https://aws-codedeploy-ap-northeast-1.s3.ap-northeast-1.amazonaws.com/latest/install
chmod +x ./install
sudo ./install auto
rm install

# CodeDeploy Agentのステータス確認と自動起動設定
echo "8. Configuring CodeDeploy Agent..."
sudo systemctl enable codedeploy-agent
sudo systemctl start codedeploy-agent

# Dockerネットワークの作成
echo "9. Creating Docker network..."
docker network create clean-todo-network 2>/dev/null || echo "Network already exists"

# バックアップディレクトリの作成
echo "10. Creating backup directory..."
mkdir -p /home/ec2-user/backups

echo ""
echo "================================"
echo "Setup Complete!"
echo "================================"
echo ""
echo "Installed versions:"
docker --version
docker-compose --version
aws --version
sudo systemctl status codedeploy-agent --no-pager
echo ""
echo "IMPORTANT: You need to logout and login again for docker group changes to take effect!"
echo "After re-login, verify with: docker ps"
echo ""
echo "Next steps:"
echo "1. Logout: exit"
echo "2. Login again: ssh -i clean-todo-key.pem ec2-user@<EC2-IP>"
echo "3. Create /home/ec2-user/.env.production file"
echo "4. Test deployment"
echo "================================"
