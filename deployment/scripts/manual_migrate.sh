#!/bin/bash
# 本番環境で手動でマイグレーションを実行するスクリプト

set -e

echo "====== Manual Database Migration ======"

# 現在のマイグレーション状態を確認
echo "Current migration status:"
docker exec clean-todo-app php artisan migrate:status

echo ""
read -p "Do you want to run migrations? (yes/no): " -r
if [[ ! $REPLY =~ ^yes$ ]]; then
    echo "Migration cancelled."
    exit 0
fi

# マイグレーションを実行
docker exec clean-todo-app php artisan migrate --force

echo "Migration completed successfully"
