#!/bin/bash
# 本番環境でキャッシュをクリアするスクリプト

set -e

echo "====== Clearing Application Cache ======"

# 設定キャッシュのクリア
echo "Clearing config cache..."
docker exec clean-todo-app php artisan config:clear

# ルートキャッシュのクリア
echo "Clearing route cache..."
docker exec clean-todo-app php artisan route:clear

# ビューキャッシュのクリア
echo "Clearing view cache..."
docker exec clean-todo-app php artisan view:clear

# アプリケーションキャッシュのクリア
echo "Clearing application cache..."
docker exec clean-todo-app php artisan cache:clear

# 再度キャッシュを生成（オプション）
read -p "Do you want to regenerate cache? (yes/no): " -r
if [[ $REPLY =~ ^yes$ ]]; then
    echo "Regenerating config cache..."
    docker exec clean-todo-app php artisan config:cache

    echo "Regenerating route cache..."
    docker exec clean-todo-app php artisan route:cache

    echo "Regenerating view cache..."
    docker exec clean-todo-app php artisan view:cache
fi

echo "Cache cleared successfully"
