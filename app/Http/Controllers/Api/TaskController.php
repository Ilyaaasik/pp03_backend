<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $taskService) {}

    public function index(Request $request, int $project): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'done'])],
            'tag_id' => [
                'sometimes', 'integer', 'min:1',
                Rule::exists('tags', 'id')->where('user_id', $request->user()->id),
            ],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return response()->json(
            $this->taskService->list($request->user(), $project, $filters),
        );
    }

    public function store(Request $request, int $project): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'done'])],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'tag_ids' => ['sometimes', 'array', 'list', 'max:50'],
            'tag_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        return response()->json([
            'data' => $this->taskService->create($request->user(), $project, $data),
        ], 201);
    }

    public function show(Request $request, int $task): JsonResponse
    {
        return response()->json([
            'data' => $this->taskService->find($request->user(), $task),
        ]);
    }

    public function update(Request $request, int $task): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'done'])],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'tag_ids' => ['sometimes', 'array', 'list', 'max:50'],
            'tag_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        return response()->json([
            'data' => $this->taskService->update($request->user(), $task, $data),
        ]);
    }

    public function destroy(Request $request, int $task): Response
    {
        $this->taskService->delete($request->user(), $task);

        return response()->noContent();
    }
}
