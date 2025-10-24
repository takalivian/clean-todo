#!/bin/bash

# Laravelプロジェクトの初期化スクリプト

echo "Laravelプロジェクトを初期化しています..."

# Laravelプロジェクトがまだ存在しない場合は作成
if [ ! -f "artisan" ]; then
    echo "新しいLaravelプロジェクトを作成します..."

    # 一時ディレクトリにLaravelをインストール
    docker run --rm -v $(pwd):/app -w /app composer create-project laravel/laravel temp-laravel --prefer-dist

    # ファイルを移動
    mv temp-laravel/* temp-laravel/.[^.]* . 2>/dev/null || true
    rmdir temp-laravel

    # .envファイルの設定
    if [ -f ".env" ]; then
        # macOS対応
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' 's/DB_HOST=.*/DB_HOST=db/' .env
            sed -i '' 's/DB_DATABASE=.*/DB_DATABASE=laravel/' .env
            sed -i '' 's/DB_USERNAME=.*/DB_USERNAME=laravel/' .env
            sed -i '' 's/DB_PASSWORD=.*/DB_PASSWORD=secret/' .env
        else
            sed -i 's/DB_HOST=.*/DB_HOST=db/' .env
            sed -i 's/DB_DATABASE=.*/DB_DATABASE=laravel/' .env
            sed -i 's/DB_USERNAME=.*/DB_USERNAME=laravel/' .env
            sed -i 's/DB_PASSWORD=.*/DB_PASSWORD=secret/' .env
        fi
    fi

    echo "Laravelのインストールが完了しました！"
else
    echo "既存のLaravelプロジェクトを検出しました。"
fi

# ストレージディレクトリの作成とパーミッション設定
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p bootstrap/cache

echo ""
echo "セットアップが完了しました！"
echo ""
echo "次のコマンドでDockerコンテナを起動してください："
echo "  docker-compose up -d"
echo ""
echo "アプリケーションは http://localhost:8080 でアクセスできます。"
