<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    public function __construct(private readonly TagService $tagService) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return response()->json($this->tagService->list($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:50',
                // Одинаковое имя разрешено разным пользователям.
                Rule::unique('tags', 'name')->where('user_id', $request->user()->id),
            ],
        ]);

        return response()->json([
            'data' => $this->tagService->create($request->user(), $data['name']),
        ], 201);
    }

    public function destroy(Request $request, int $tag): Response
    {
        $this->tagService->delete($request->user(), $tag);

        return response()->noContent();
    }
}
