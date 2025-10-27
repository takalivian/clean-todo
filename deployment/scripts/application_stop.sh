#!/bin/bash
set -e

echo "====== Application Stop ======"

# 実行中のコンテナを停止
if [ "$(docker ps -q -f name=clean-todo-app)" ]; then
    echo "Stopping running container..."
    docker stop clean-todo-app || true
    docker rm clean-todo-app || true
else
    echo "No running container found"
fi

echo "Application stop completed successfully"
