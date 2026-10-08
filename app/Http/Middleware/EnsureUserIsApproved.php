<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'احراز هویت الزامی است.',
            ], 401);
        }

        if ($user->role !== 'USER') {
            return response()->json([
                'success' => false,
                'message' => 'دسترسی غیرمجاز است.',
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'حساب کاربری شما غیرفعال است.',
            ], 403);
        }

        if ($user->status !== 'APPROVED') {
            return response()->json([
                'success' => false,
                'message' => 'حساب کاربری شما هنوز تأیید نشده است.',
                'status' => $user->status,
            ], 403);
        }

        return $next($request);
    }
}