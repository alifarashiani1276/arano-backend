<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
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

        if ($user->role !== 'SUPER_ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'فقط سوپر ادمین به این بخش دسترسی دارد.',
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'حساب سوپر ادمین غیرفعال است.',
            ], 403);
        }

        if ($user->status !== 'APPROVED') {
            return response()->json([
                'success' => false,
                'message' => 'پروفایل سوپر ادمین هنوز تکمیل نشده است.',
            ], 403);
        }

        $token = $request->user()->currentAccessToken();

        if ($token && !$token->can('admin') && !$token->can('*')) {
            return response()->json([
                'success' => false,
                'message' => 'توکن دسترسی کامل مدیر را ندارد.',
            ], 403);
        }

        return $next($request);
    }
}