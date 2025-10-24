#!/bin/bash
set -e

# Laravelプロジェクトが存在しない場合はインストール
if [ ! -f "artisan" ]; then
    echo "Laravelプロジェクトが見つかりません。インストールします..."

    # 一時ディレクトリにLaravelをインストール
    composer create-project laravel/laravel /tmp/laravel --prefer-dist

    # ファイルを移動（隠しファイルも含む）
    shopt -s dotglob
    mv /tmp/laravel/* /var/www/html/
    rmdir /tmp/laravel

    # .envファイルの設定
    if [ -f ".env" ]; then
        sed -i 's/DB_HOST=.*/DB_HOST=db/' .env
        sed -i 's/DB_DATABASE=.*/DB_DATABASE=laravel/' .env
        sed -i 's/DB_USERNAME=.*/DB_USERNAME=laravel/' .env
        sed -i 's/DB_PASSWORD=.*/DB_PASSWORD=secret/' .env
    fi

    # パーミッション設定
    chown -R www-data:www-data /var/www/html
    chmod -R 755 /var/www/html/storage
    chmod -R 755 /var/www/html/bootstrap/cache

    echo "Laravelのインストールが完了しました！"
fi

# アプリケーションキーの生成（未設定の場合）
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    php artisan key:generate
fi

# Laravelの開発サーバーを起動
echo "Laravelサーバーを起動しています..."
php artisan serve --host=0.0.0.0 --port=8080
