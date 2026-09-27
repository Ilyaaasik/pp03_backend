<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(private readonly ProjectService $projectService) {}

    /** @param array{status?: string, tag_id?: int|string, page?: int|string} $filters */
    public function list(User $user, int $projectId, array $filters): LengthAwarePaginator
    {
        $project = $this->projectService->find($user, $projectId);

        // Загружаем теги сразу, без отдельного запроса на каждую задачу.
        $query = Task::query()->where('project_id', $project->id)->with('tags');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['tag_id'])) {
            $query->whereHas('tags', function (Builder $query) use ($filters): void {
                $query->where('tags.id', $filters['tag_id']);
            });
        }

        return $query->orderByDesc('id')->paginate(15)
            ->appends(Arr::only($filters, ['status', 'tag_id']));
    }

    public function find(User $user, int $taskId): Task
    {
        return Task::query()
            ->whereHas('project', function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id);
            })
            ->with('tags')
            ->findOrFail($taskId);
    }

    /** @param array{title: string, description?: string|null, status?: string, due_date?: string|null, tag_ids?: array<int, int|string>} $data */
    public function create(User $user, int $projectId, array $data): Task
    {
        $project = $this->projectService->find($user, $projectId);

        // Задача и её теги сохраняются вместе либо полностью откатываются.
        return DB::transaction(function () use ($user, $project, $data): Task {
            $tagIds = $data['tag_ids'] ?? [];
            $this->assertTagsBelongToUser($user, $tagIds);

            $task = Task::query()->create([
                'project_id' => $project->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'todo',
                'due_date' => $data['due_date'] ?? null,
            ]);
            $task->tags()->sync($tagIds);

            return $task->load('tags');
        });
    }

    /** @param array{title?: string, description?: string|null, status?: string, due_date?: string|null, tag_ids?: array<int, int|string>} $data */
    public function update(User $user, int $taskId, array $data): Task
    {
        $task = $this->find($user, $taskId);

        return DB::transaction(function () use ($user, $task, $data): Task {
            if (array_key_exists('tag_ids', $data)) {
                $this->assertTagsBelongToUser($user, $data['tag_ids']);
            }

            $task->update(Arr::only($data, ['title', 'description', 'status', 'due_date']));

            // Нет tag_ids — сохраняем теги; пустой массив — снимаем все.
            if (array_key_exists('tag_ids', $data)) {
                $task->tags()->sync($data['tag_ids']);
            }

            return $task->refresh()->load('tags');
        });
    }

    public function delete(User $user, int $taskId): void
    {
        $this->find($user, $taskId)->delete();
    }

    /** @param array<int, int|string> $tagIds */
    private function assertTagsBelongToUser(User $user, array $tagIds): void
    {
        // Блокировка удерживает теги от удаления до сохранения связей.
        $tags = Tag::query()->where('user_id', $user->id)
            ->whereIn('id', $tagIds)->sharedLock()->get(['id']);

        if ($tags->count() !== count($tagIds)) {
            throw ValidationException::withMessages([
                'tag_ids' => ['Один или несколько тегов не существуют или вам недоступны.'],
            ]);
        }
    }
}
