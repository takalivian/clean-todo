#!/bin/bash
set -e

echo "====== Validate Service ======"

# コンテナが起動しているか確認
if [ ! "$(docker ps -q -f name=clean-todo-app)" ]; then
    echo "ERROR: Application container is not running"
    exit 1
fi

# アプリケーションのヘルスチェック
echo "Checking application health..."
MAX_ATTEMPTS=30
ATTEMPT=0

while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    if curl -f http://localhost/api/health > /dev/null 2>&1; then
        echo "Application is healthy"
        exit 0
    fi

    ATTEMPT=$((ATTEMPT + 1))
    echo "Attempt $ATTEMPT/$MAX_ATTEMPTS: Application not ready yet..."
    sleep 2
done

echo "ERROR: Application failed health check"
exit 1
