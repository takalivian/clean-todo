<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    // ステータス定数
    const STATUS_PENDING = 0; // 未着手
    const STATUS_IN_PROGRESS = 1; // 進行中
    const STATUS_COMPLETED = 2; // 完了

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'due_date',
        'completed_at',
        'updated_by',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * このタスクを所有するユーザー
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * このタスクを最後に更新したユーザー
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * ステータスを文字列に変換
     */
    public function getStatusAttribute($value)
    {
        $statusMap = [
            self::STATUS_PENDING => 'pending',
            self::STATUS_IN_PROGRESS => 'in_progress',
            self::STATUS_COMPLETED => 'completed',
        ];

        return $statusMap[$value] ?? 'unknown';
    }
}
