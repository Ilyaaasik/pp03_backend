<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projectService) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return response()->json($this->projectService->list($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        return response()->json([
            'data' => $this->projectService->create($request->user(), $data),
        ], 201);
    }

    public function show(Request $request, int $project): JsonResponse
    {
        return response()->json([
            'data' => $this->projectService->find($request->user(), $project),
        ]);
    }

    public function update(Request $request, int $project): JsonResponse
    {
        // sometimes: отсутствующие в PATCH поля остаются прежними.
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        return response()->json([
            'data' => $this->projectService->update($request->user(), $project, $data),
        ]);
    }

    public function destroy(Request $request, int $project): Response
    {
        $this->projectService->delete($request->user(), $project);

        return response()->noContent();
    }
}
