<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Task::query();

        // クエリパラメータで削除済みの扱いを制御
        if ($request->boolean('only_deleted')) {
            // 削除済みのみ取得
            $query->onlyTrashed();
        } elseif ($request->boolean('with_deleted')) {
            // すべて取得（削除済み含む）
            $query->withTrashed();
        }
        // デフォルトは削除されていないタスクのみ

        $tasks = $query->orderBy('due_date', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $tasks
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|integer|min:0|max:2',
            'due_date' => 'nullable|date',
        ]);

        $task = Task::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'タスクが作成されました',
            'data' => $task
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'タスクが見つかりません'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $task
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'タスクが見つかりません'
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|integer|min:0|max:2',
            'due_date' => 'nullable|date',
        ]);

        // ステータスが変更される場合、completed_atを自動設定
        if (isset($validated['status'])) {
            if ($validated['status'] == 2) {
                // ステータスが完了の場合、completed_atに現在時刻を設定
                $validated['completed_at'] = now();
            } else {
                // それ以外の場合、completed_atをnullに設定
                $validated['completed_at'] = null;
            }
        }

        $task->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'タスクが更新されました',
            'data' => $task
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'タスクが見つかりません'
            ], 404);
        }

        $task->delete(); // 論理削除

        return response()->json([
            'success' => true,
            'message' => 'タスクが削除されました'
        ]);
    }

    /**
     * タスクを完了状態にする
     */
    public function complete(string $id)
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => 'タスクが見つかりません'
            ], 404);
        }

        $task->update([
            'status' => 2,
            'completed_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'タスクが完了しました',
            'data' => $task
        ]);
    }

    /**
     * 削除されたタスクを復元する
     */
    public function restore(string $id)
    {
        $task = Task::onlyTrashed()->find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'message' => '削除されたタスクが見つかりません'
            ], 404);
        }

        $task->restore();

        return response()->json([
            'success' => true,
            'message' => 'タスクが復元されました',
            'data' => $task
        ]);
    }
}
