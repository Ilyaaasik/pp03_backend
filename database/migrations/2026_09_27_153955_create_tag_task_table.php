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
        Schema::create('tag_task', function (Blueprint $table): void {
            // связь между этой таблицой и задачей
            $table->foreignId('task_id')
                ->constrained('tasks')
                ->cascadeOnDelete();

            // связь между этой таблицой и тегом
            $table->foreignId('tag_id')
                ->constrained('tags')
                ->cascadeOnDelete();

            // ограничение: первичные ключи
            $table->primary(['task_id', 'tag_id']);

            // индекс
            $table->index('tag_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tag_task');
    }
};
