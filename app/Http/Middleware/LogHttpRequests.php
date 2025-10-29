<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogHttpRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // リクエスト開始時刻を記録
        $startTime = microtime(true);

        // リクエストを処理
        $response = $next($request);

        // 処理時間を計算（ミリ秒）
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        // ログに記録する情報を準備
        $logData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
            'status' => $response->getStatusCode(),
            'execution_time' => $executionTime . 'ms',
        ];

        // リクエストボディ（パスワードなどの機密情報は除外）
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $logData['request_data'] = $this->filterSensitiveData($request->all());
        }

        // アクセスログ専用チャンネルに記録
        // ログレベルを決定（エラーの場合はerror、それ以外はinfo）
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 500) {
            Log::channel('access')->error('HTTP Request', $logData);
        } elseif ($statusCode >= 400) {
            Log::channel('access')->warning('HTTP Request', $logData);
        } else {
            Log::channel('access')->info('HTTP Request', $logData);
        }

        return $response;
    }

    /**
     * 機密情報をフィルタリング
     *
     * @param array $data
     * @return array
     */
    private function filterSensitiveData(array $data): array
    {
        $sensitiveKeys = ['password', 'password_confirmation', 'token', 'secret', 'api_key'];

        foreach ($sensitiveKeys as $key) {
            if (isset($data[$key])) {
                $data[$key] = '***FILTERED***';
            }
        }

        return $data;
    }
}
