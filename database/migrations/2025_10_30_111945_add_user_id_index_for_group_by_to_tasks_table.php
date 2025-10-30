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
        // GROUP BY user_id 専用のインデックス
        // 注: 外部キーインデックス tasks_user_id_foreign が既に存在する場合はスキップ
        try {
            DB::statement('CREATE INDEX tasks_user_id_covering_index ON tasks(user_id)');
        } catch (\Exception $e) {
            // 既にインデックスが存在する場合はスキップ
            if (str_contains($e->getMessage(), 'Duplicate key name')) {
                // インデックスが既に存在する場合は何もしない
                return;
            }
            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_user_id_covering_index');
        });
    }
};
