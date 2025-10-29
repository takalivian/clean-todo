<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class DailyDirectoryLogger
{
    /**
     * カスタムMonologインスタンスを作成
     *
     * @param  array  $config
     * @return \Monolog\Logger
     */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger($config['name'] ?? 'daily-directory');

        // 日付ごとのディレクトリパスを生成
        $dateDir = now()->format('Y-m-d');
        $logPath = storage_path("logs/{$dateDir}/{$config['filename']}");

        // ディレクトリが存在しない場合は作成
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // StreamHandlerを作成
        $handler = new StreamHandler(
            $logPath,
            $config['level'] ?? Logger::DEBUG
        );

        // フォーマッターを設定
        $formatter = new LineFormatter(
            null, // デフォルトのフォーマット
            null, // デフォルトの日付フォーマット
            true, // 改行を許可
            true  // スタックトレースを含める
        );
        $handler->setFormatter($formatter);

        $logger->pushHandler($handler);

        return $logger;
    }
}
