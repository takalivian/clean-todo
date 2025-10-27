#!/bin/bash
# データベースのバックアップスクリプト

set -e

echo "====== Database Backup ======"

# バックアップディレクトリの作成
BACKUP_DIR="/home/ec2-user/backups"
mkdir -p $BACKUP_DIR

# タイムスタンプを生成
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/clean-todo-db-$TIMESTAMP.sql"

# データベース接続情報を取得
DB_NAME=${DB_DATABASE:-laravel}
DB_USER=${DB_USERNAME:-laravel}
DB_PASS=${DB_PASSWORD:-secret}

# データベースをバックアップ
echo "Creating backup: $BACKUP_FILE"
docker exec clean-todo-db mysqldump -u$DB_USER -p$DB_PASS $DB_NAME > $BACKUP_FILE

# バックアップを圧縮
gzip $BACKUP_FILE

echo "Backup completed: ${BACKUP_FILE}.gz"
echo "Backup size: $(du -h ${BACKUP_FILE}.gz | cut -f1)"

# 古いバックアップを削除（30日以上前のものを削除）
echo "Cleaning up old backups (older than 30 days)..."
find $BACKUP_DIR -name "clean-todo-db-*.sql.gz" -type f -mtime +30 -delete

echo "Remaining backups:"
ls -lh $BACKUP_DIR/clean-todo-db-*.sql.gz 2>/dev/null || echo "No backups found"
