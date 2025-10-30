FROM php:8.2-fpm

# 作業ディレクトリを設定
WORKDIR /var/www/html

# システムパッケージをインストール
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# PHP拡張機能をインストール
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Xdebug をインストールして有効化
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Xdebug 設定ファイルを配置
COPY docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Composerをインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# エントリーポイントスクリプトをコピー
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# ポート8080を公開
EXPOSE 8080

# エントリーポイントを設定
ENTRYPOINT ["docker-entrypoint.sh"]
