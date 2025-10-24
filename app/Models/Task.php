<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'title',
        'description',
        'status',
        'due_date',
        'completed_at',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * ステータスを文字列に変換
     */
    public function getStatusAttribute($value)
    {
        $statusMap = [
            0 => 'pending',    // 未着手
            1 => 'in_progress', // 進行中
            2 => 'completed',   // 完了
        ];

        return $statusMap[$value] ?? 'unknown';
    }
}
