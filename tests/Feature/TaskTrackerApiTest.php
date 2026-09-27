<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskTrackerApiTest extends TestCase
{
    // phpunit.xml использует SQLite в памяти, а не рабочую PostgreSQL.
    use RefreshDatabase;

    public function test_registration_hashes_password_and_issues_a_working_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ильяс',
            'email' => 'ilyas@example.com',
            'password' => 'LearningApi123!',
            'password_confirmation' => 'LearningApi123!',
            'is_active' => false,
        ])->assertCreated()->assertJsonPath('data.user.is_active', true)
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token');

        $user = User::query()->firstOrFail();
        $this->assertTrue(Hash::check('LearningApi123!', $user->password));
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNotNull($user->tokens()->firstOrFail()->expires_at);

        $this->withToken($response->json('data.token'))->getJson('/api/me')
            ->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_login_validation_and_logout_only_revoke_the_current_token(): void
    {
        $user = User::factory()->create(['email' => 'login@example.com']);

        $this->postJson('/api/login', [
            'email' => $user->email, 'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $response = $this->postJson('/api/login', [
            'email' => $user->email, 'password' => 'password',
        ])->assertOk();

        $otherToken = $user->createToken('other-device');
        $token = $response->json('data.token');

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);

        // Новый запрос должен заново проверить уже отозванный токен.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_authentication_blocked_accounts_and_rate_limit(): void
    {
        // API возвращает JSON даже без заголовка Accept.
        $this->get('/api/projects')->assertUnauthorized()->assertJsonStructure(['message']);

        $user = User::factory()->create(['is_active' => false]);
        $this->postJson('/api/login', [
            'email' => $user->email, 'password' => 'password',
        ])->assertForbidden();

        $token = $user->createToken('blocked')->plainTextToken;
        $this->withToken($token)->getJson('/api/projects')->assertForbidden();
        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/register', [])->assertUnprocessable();
        }
        $this->postJson('/api/register', [])->assertStatus(429);
    }

    public function test_project_crud_uses_authenticated_owner_and_partial_updates(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/projects', [])->assertUnprocessable();

        $projectId = $this->postJson('/api/projects', [
            'name' => 'Учебный проект', 'description' => 'Описание', 'user_id' => $other->id,
        ])->assertCreated()->assertJsonPath('data.user_id', $user->id)->json('data.id');

        $this->patchJson("/api/projects/$projectId", ['name' => 'Новое название'])
            ->assertOk()->assertJsonPath('data.description', 'Описание');
        $this->getJson("/api/projects/$projectId")->assertOk();
        $this->getJson('/api/projects')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/projects/$projectId")->assertNoContent();
        $this->getJson("/api/projects/$projectId")->assertNotFound();
    }

    public function test_foreign_records_are_inaccessible_through_all_routes(): void
    {
        $owner = User::factory()->create();
        $project = Project::query()->create(['user_id' => $owner->id, 'name' => 'Чужой']);
        $task = Task::query()->create(['project_id' => $project->id, 'title' => 'Чужая']);
        $comment = Comment::query()->create([
            'task_id' => $task->id, 'user_id' => $owner->id, 'body' => 'Чужой',
        ]);
        $tag = Tag::query()->create(['user_id' => $owner->id, 'name' => 'private']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/projects/$project->id")->assertNotFound();
        $this->patchJson("/api/projects/$project->id", ['name' => 'Hack'])->assertNotFound();
        $this->deleteJson("/api/projects/$project->id")->assertNotFound();
        $this->getJson("/api/projects/$project->id/tasks")->assertNotFound();
        $this->postJson("/api/projects/$project->id/tasks", ['title' => 'Hack'])->assertNotFound();
        $this->getJson("/api/tasks/$task->id")->assertNotFound();
        $this->patchJson("/api/tasks/$task->id", ['status' => 'done'])->assertNotFound();
        $this->deleteJson("/api/tasks/$task->id")->assertNotFound();
        $this->getJson("/api/tasks/$task->id/comments")->assertNotFound();
        $this->postJson("/api/tasks/$task->id/comments", ['body' => 'Hack'])->assertNotFound();
        $this->deleteJson("/api/comments/$comment->id")->assertNotFound();
        $this->deleteJson("/api/tags/$tag->id")->assertNotFound();
        $this->getJson('/api/projects')->assertJsonCount(0, 'data');
        $this->getJson('/api/tags')->assertJsonCount(0, 'data');
    }

    public function test_tasks_support_tags_filters_and_partial_updates(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = Project::query()->create(['user_id' => $user->id, 'name' => 'Проект']);
        $tag = Tag::query()->create(['user_id' => $user->id, 'name' => 'backend']);

        $taskId = $this->postJson("/api/projects/$project->id/tasks", [
            'title' => 'API', 'due_date' => '2026-10-15', 'tag_ids' => [$tag->id],
        ])->assertCreated()->assertJsonPath('data.status', 'todo')
            ->assertJsonPath('data.due_date', '2026-10-15')
            ->assertJsonPath('data.tags.0.id', $tag->id)->json('data.id');

        $this->patchJson("/api/tasks/$taskId", ['status' => 'done'])
            ->assertOk()->assertJsonCount(1, 'data.tags');
        $this->getJson("/api/projects/$project->id/tasks?status=done&tag_id=$tag->id")
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/projects/$project->id/tasks?status=todo")
            ->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/tasks/$taskId", ['tag_ids' => [], 'due_date' => null])
            ->assertOk()->assertJsonCount(0, 'data.tags')->assertJsonPath('data.due_date', null);
    }

    public function test_invalid_tags_do_not_create_or_partially_update_a_task(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = Project::query()->create(['user_id' => $user->id, 'name' => 'Проект']);
        $tag = Tag::query()->create(['user_id' => $user->id, 'name' => 'mine']);
        $foreignTag = Tag::query()->create(['user_id' => User::factory()->create()->id, 'name' => 'other']);

        $this->postJson("/api/projects/$project->id/tasks", [
            'title' => 'API', 'tag_ids' => [$foreignTag->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('tag_ids');
        $this->assertDatabaseCount('tasks', 0);

        $taskId = $this->postJson("/api/projects/$project->id/tasks", [
            'title' => 'Исходная', 'tag_ids' => [$tag->id],
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/tasks/$taskId", [
            'title' => 'Изменённая', 'tag_ids' => [$foreignTag->id],
        ])->assertUnprocessable();
        $this->assertDatabaseHas('tasks', ['id' => $taskId, 'title' => 'Исходная']);
        $this->assertDatabaseHas('tag_task', ['task_id' => $taskId, 'tag_id' => $tag->id]);

        $this->postJson("/api/projects/$project->id/tasks", [
            'title' => 'API', 'status' => 'invalid', 'due_date' => '2026-02-30',
            'tag_ids' => [$tag->id, $tag->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['status', 'due_date', 'tag_ids.0']);
    }

    public function test_comments_and_cascade_deletion(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = Project::query()->create(['user_id' => $user->id, 'name' => 'Проект']);
        $task = Task::query()->create(['project_id' => $project->id, 'title' => 'API']);
        $tag = Tag::query()->create(['user_id' => $user->id, 'name' => 'backend']);
        $task->tags()->attach($tag);

        $commentId = $this->postJson("/api/tasks/$task->id/comments", ['body' => 'Готово'])
            ->assertCreated()->json('data.id');
        $this->getJson("/api/tasks/$task->id/comments")->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/comments/$commentId")->assertNoContent();
        $this->postJson("/api/tasks/$task->id/comments", ['body' => 'Второй'])->assertCreated();

        $this->deleteJson("/api/tasks/$task->id")->assertNoContent();
        $this->assertDatabaseCount('comments', 0);
        $this->assertDatabaseCount('tag_task', 0);
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);

        $task = Task::query()->create(['project_id' => $project->id, 'title' => 'Вторая']);
        $task->tags()->attach($tag);
        $this->postJson("/api/tasks/$task->id/comments", ['body' => 'Каскад'])->assertCreated();
        $this->deleteJson("/api/projects/$project->id")->assertNoContent();
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('comments', 0);
        $this->assertDatabaseCount('tag_task', 0);
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
    }

    public function test_tags_are_unique_per_user_and_deleting_a_tag_preserves_tasks(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $tagId = $this->postJson('/api/tags', ['name' => 'backend'])->assertCreated()->json('data.id');
        $this->postJson('/api/tags', ['name' => 'backend'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');

        $project = Project::query()->create(['user_id' => $user->id, 'name' => 'Проект']);
        $task = Task::query()->create(['project_id' => $project->id, 'title' => 'API']);
        $task->tags()->attach($tagId);
        $this->deleteJson("/api/tags/$tagId")->assertNoContent();
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertDatabaseCount('tag_task', 0);

        $this->postJson('/api/tags', ['name' => 'backend'])->assertCreated();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/tags', ['name' => 'backend'])->assertCreated();
    }
}
