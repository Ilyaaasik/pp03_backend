<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        // Сначала auth:sanctum определяет пользователя, затем проверяем блокировку.
        abort_unless($request->user()?->is_active, 403, 'Аккаунт заблокирован.');

        return $next($request);
    }
}
