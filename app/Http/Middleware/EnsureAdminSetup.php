<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminSetup
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'احراز هویت الزامی است.',
            ], 401);
        }

        if (!in_array($user->role, ['ADMIN', 'SUPER_ADMIN'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'دسترسی غیرمجاز است.',
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'حساب مدیر غیرفعال است.',
            ], 403);
        }

        if ($user->status !== 'PENDING') {
            return response()->json([
                'success' => false,
                'message' => 'این حساب نیاز به تکمیل اولیه ندارد.',
            ], 403);
        }

        $token = $user->currentAccessToken();

        if (!$token || !$token->can('admin:setup')) {
            return response()->json([
                'success' => false,
                'message' => 'توکن تکمیل پروفایل معتبر نیست.',
            ], 403);
        }

        return $next($request);
    }
}