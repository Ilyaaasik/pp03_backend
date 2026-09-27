<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    // Поля, разрешённые для массового заполнения.
    protected $fillable = ['project_id', 'title', 'description', 'status', 'due_date'];

    protected function casts(): array
    {
        return ['due_date' => 'date:Y-m-d'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tags(): BelongsToMany
    {
        // Eloquent сохраняет связи в промежуточной таблице.
        return $this->belongsToMany(Tag::class, 'tag_task');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
