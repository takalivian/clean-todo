<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ログクリーンアップのスケジュール設定
// 毎日午前2時に14日より古いログを削除
Schedule::command('logs:clean', ['--days' => 14])
    ->dailyAt('02:00')
    ->timezone('Asia/Tokyo')
    ->onSuccess(function () {
        \Log::info('古いログの削除が完了しました');
    })
    ->onFailure(function () {
        \Log::error('古いログの削除に失敗しました');
    });
