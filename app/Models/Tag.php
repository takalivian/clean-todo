<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tag extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'user_id',
        'updated_by',
    ];

    /**
     * このタグを作成したユーザー
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * このタグを最後に更新したユーザー
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * このタグが付けられているタスク（多対多）
     */
    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'task_tag')
                    ->withTimestamps();
    }
}
