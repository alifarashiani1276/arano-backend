<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileCompleted
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

        $profileCompleted =
            !empty($user->first_name) &&
            !empty($user->last_name) &&
            !empty($user->email);

        if (!$profileCompleted) {
            return response()->json([
                'success' => false,
                'message' => 'ابتدا باید پروفایل خود را تکمیل کنید.',
                'data' => [
                    'profile_completed' => false,
                ],
            ], 403);
        }

        return $next($request);
    }
}