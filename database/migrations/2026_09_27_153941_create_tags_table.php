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
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();

            // связь между тегом и пользователем
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // название тега
            $table->string('name', 50);

            // дата и время создания
            $table->timestamps();

            // ограничение: уникальность
            $table->unique(['user_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
