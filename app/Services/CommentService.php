<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CommentService
{
    public function __construct(private readonly TaskService $taskService) {}

    public function list(User $user, int $taskId): LengthAwarePaginator
    {
        $task = $this->taskService->find($user, $taskId);

        return Comment::query()->where('task_id', $task->id)
            ->orderBy('created_at')->orderBy('id')->paginate(20);
    }

    public function create(User $user, int $taskId, string $body): Comment
    {
        // Комментировать можно только доступную пользователю задачу.
        $task = $this->taskService->find($user, $taskId);

        return Comment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => $body,
        ]);
    }

    public function delete(User $user, int $commentId): void
    {
        $comment = Comment::query()->where('user_id', $user->id)->findOrFail($commentId);
        $this->taskService->find($user, $comment->task_id);
        $comment->delete();
    }
}
