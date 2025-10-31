<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class CleanOldLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:clean {--days=14 : ログの保持日数}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '指定された日数より古いログディレクトリを削除します';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $logPath = storage_path('logs');

        $this->info("ログディレクトリをスキャン中: {$logPath}");
        $this->info("{$days}日より古いログを削除します...");

        // ログディレクトリ内のサブディレクトリを取得
        $directories = File::directories($logPath);
        $deletedCount = 0;
        $deletedSize = 0;

        foreach ($directories as $directory) {
            $dirName = basename($directory);

            // 日付形式（YYYY-MM-DD）のディレクトリのみ処理
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dirName)) {
                try {
                    $dirDate = Carbon::createFromFormat('Y-m-d', $dirName);
                    $cutoffDate = now()->subDays($days);

                    // 指定日数より古い場合は削除
                    if ($dirDate->lt($cutoffDate)) {
                        // ディレクトリのサイズを計算
                        $size = $this->getDirectorySize($directory);
                        $deletedSize += $size;

                        // ディレクトリを削除
                        File::deleteDirectory($directory);

                        $this->line("✓ 削除: {$dirName} (" . $this->formatBytes($size) . ")");
                        $deletedCount++;
                    }
                } catch (\Exception $e) {
                    $this->error("エラー: {$dirName} - " . $e->getMessage());
                }
            }
        }

        if ($deletedCount > 0) {
            $this->info("\n合計 {$deletedCount} 個のディレクトリを削除しました。");
            $this->info("削除されたサイズ: " . $this->formatBytes($deletedSize));
        } else {
            $this->info("\n削除対象のログディレクトリはありませんでした。");
        }

        return Command::SUCCESS;
    }

    /**
     * ディレクトリのサイズを取得
     *
     * @param string $directory
     * @return int
     */
    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        $files = File::allFiles($directory);

        foreach ($files as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    /**
     * バイト数を人間が読みやすい形式に変換
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
