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
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();

            // связи между задачи и проектом
            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            // название задачи
            $table->string('title', 200);
            // описание задачи
            $table->text('description')->nullable();

            // статус задачи
            $table->enum('status', [
                'todo',
                'in_progress',
                'done',
            ])->default('todo');

            // дата выполнения
            $table->date('due_date')->nullable();

            // дата и время создания
            $table->timestamps();

            // составной индекс
            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
