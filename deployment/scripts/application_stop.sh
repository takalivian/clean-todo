#!/bin/bash
set -e

echo "====== Application Stop ======"

# 実行中または停止中のコンテナを削除
if [ "$(docker ps -aq -f name=clean-todo-app)" ]; then
    echo "Stopping and removing existing container..."
    docker stop clean-todo-app 2>/dev/null || true
    docker rm -f clean-todo-app 2>/dev/null || true
else
    echo "No existing container found"
fi

echo "Application stop completed successfully"
