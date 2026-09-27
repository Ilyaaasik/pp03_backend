<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CommentController extends Controller
{
    public function __construct(private readonly CommentService $commentService) {}

    public function index(Request $request, int $task): JsonResponse
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return response()->json($this->commentService->list($request->user(), $task));
    }

    public function store(Request $request, int $task): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        return response()->json([
            'data' => $this->commentService->create($request->user(), $task, $data['body']),
        ], 201);
    }

    public function destroy(Request $request, int $comment): Response
    {
        $this->commentService->delete($request->user(), $comment);

        return response()->noContent();
    }
}
