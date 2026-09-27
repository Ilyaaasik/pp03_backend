<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class ProjectService
{
    public function list(User $user): LengthAwarePaginator
    {
        return Project::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function find(User $user, int $projectId): Project
    {
        // Чужие записи не раскрываем: для пользователя они не найдены (404).
        return Project::query()
            ->where('user_id', $user->id)
            ->findOrFail($projectId);
    }

    /** @param array{name: string, description?: string|null} $data */
    public function create(User $user, array $data): Project
    {
        return Project::query()->create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);
    }

    /** @param array{name?: string, description?: string|null} $data */
    public function update(User $user, int $projectId, array $data): Project
    {
        $project = $this->find($user, $projectId);
        $project->update(Arr::only($data, ['name', 'description']));

        return $project->refresh();
    }

    public function delete(User $user, int $projectId): void
    {
        // Связанные задачи удаляет сама БД через cascadeOnDelete.
        $this->find($user, $projectId)->delete();
    }
}
