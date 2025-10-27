#!/bin/bash
# 本番環境で手動でデータベースをシードするスクリプト
# 注意: 本番環境でのシーディングは慎重に行ってください

set -e

echo "====== Manual Database Seeding ======"
echo "WARNING: This will seed the production database!"
read -p "Are you sure you want to continue? (yes/no): " -r
if [[ ! $REPLY =~ ^yes$ ]]; then
    echo "Seeding cancelled."
    exit 0
fi

# アプリケーションコンテナでシーダーを実行
docker exec clean-todo-app php artisan db:seed --force

echo "Database seeding completed successfully"
