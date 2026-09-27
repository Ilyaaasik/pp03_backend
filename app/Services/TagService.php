<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class TagService
{
    public function list(User $user): LengthAwarePaginator
    {
        return Tag::query()->where('user_id', $user->id)
            ->orderBy('name')->orderBy('id')->paginate(30);
    }

    public function create(User $user, string $name): Tag
    {
        try {
            return Tag::query()->create(['user_id' => $user->id, 'name' => $name]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => ['У вас уже есть тег с таким названием.'],
            ]);
        }
    }

    public function delete(User $user, int $tagId): void
    {
        // База удалит связи tag_task, но сохранит сами задачи.
        Tag::query()->where('user_id', $user->id)->findOrFail($tagId)->delete();
    }
}
