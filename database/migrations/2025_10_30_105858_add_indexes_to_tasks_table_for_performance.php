<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // 単一カラムインデックス
            $table->index('status', 'tasks_status_index');
            $table->index('deleted_at', 'tasks_deleted_at_index');
            $table->index('created_at', 'tasks_created_at_index');
            $table->index('due_date', 'tasks_due_date_index');

            // 複合インデックス（最も頻繁に使われる組み合わせ）
            // user_id + deleted_at + created_at（デフォルトのクエリパターン）
            $table->index(['user_id', 'deleted_at', 'created_at'], 'tasks_user_deleted_created_index');

            // user_id + status + deleted_at（ステータスフィルタ時）
            $table->index(['user_id', 'status', 'deleted_at'], 'tasks_user_status_deleted_index');

            // due_date範囲検索用
            $table->index(['deleted_at', 'due_date'], 'tasks_deleted_duedate_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // インデックスのリスト
        $indexes = [
            'tasks_status_index',
            'tasks_deleted_at_index',
            'tasks_created_at_index',
            'tasks_due_date_index',
            'tasks_user_deleted_created_index',
            'tasks_user_status_deleted_index',
            'tasks_deleted_duedate_index',
        ];

        foreach ($indexes as $indexName) {
            try {
                \DB::statement("ALTER TABLE tasks DROP INDEX {$indexName}");
            } catch (\Exception $e) {
                // インデックスが存在しない、または外部キー制約で使用されている場合は無視
                $errorMessage = $e->getMessage();
                if (!str_contains($errorMessage, 'check that column/key exists') &&
                    !str_contains($errorMessage, 'needed in a foreign key constraint') &&
                    !str_contains($errorMessage, "Can't DROP")) {
                    throw $e;
                }
                // エラーを無視して続行
            }
        }
    }
};
