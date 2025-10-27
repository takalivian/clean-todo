#!/bin/bash
set -e

echo "====== Before Install ======"

# 古いデプロイメントファイルをクリーンアップ
if [ -d /home/ec2-user/clean-todo ]; then
    echo "Cleaning up old deployment files..."
    rm -rf /home/ec2-user/clean-todo
fi

# 必要なディレクトリを作成
mkdir -p /home/ec2-user/clean-todo

echo "Before install completed successfully"
