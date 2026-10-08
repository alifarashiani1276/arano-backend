<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    /**
     * نمایش درخواست‌های در انتظار تأیید
     */
    public function index(): JsonResponse
    {
        $users = User::where('role', 'USER')
            ->where('status', 'PENDING')
            ->latest()
            ->get([
                'id',
                'phone',
                'first_name',
                'last_name',
                'email',
                'bio',
                'status',
                'is_active',
                'created_at',
                'updated_at',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'requests' => $users,
            ],
        ]);
    }

    /**
     * تأیید درخواست کاربر
     */
    public function approve(int $id): JsonResponse
    {
        $user = User::where('role', 'USER')
            ->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر مورد نظر یافت نشد.',
            ], 404);
        }

        /*
         * فقط درخواست‌های PENDING قابل تأیید هستند.
         */
        if ($user->status !== 'PENDING') {
            return response()->json([
                'success' => false,
                'message' => 'فقط درخواست‌های در انتظار تأیید قابل تأیید هستند.',
                'data' => [
                    'current_status' => $user->status,
                ],
            ], 422);
        }
        $profileCompleted =
            !empty($user->first_name) &&
            !empty($user->last_name) &&
            !empty($user->email);

        if (!$profileCompleted) {
            return response()->json([
                'success' => false,
                'message' => 'پروفایل کاربر هنوز تکمیل نشده است.',
                'data' => [
                    'profile_completed' => false,
             ],
            ], 422);
        }
        $user->update([
            'status' => 'APPROVED',
        ]);

        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'درخواست کاربر با موفقیت تأیید شد.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'phone' => $user->phone,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'bio' => $user->bio,
                    'status' => $user->status,
                    'is_active' => $user->is_active,
                    'updated_at' => $user->updated_at,
                ],
            ],
        ]);
    }

    /**
     * رد درخواست کاربر
     */
    public function reject(int $id): JsonResponse
    {
        $user = User::where('role', 'USER')
            ->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر مورد نظر یافت نشد.',
            ], 404);
        }

        /*
         * فقط درخواست‌های PENDING قابل رد شدن هستند.
         */
        if ($user->status !== 'PENDING') {
            return response()->json([
                'success' => false,
                'message' => 'فقط درخواست‌های در انتظار تأیید قابل رد شدن هستند.',
                'data' => [
                    'current_status' => $user->status,
                ],
            ], 422);
        }

        $user->update([
            'status' => 'REJECTED',
        ]);

        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'درخواست کاربر با موفقیت رد شد.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'phone' => $user->phone,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'bio' => $user->bio,
                    'status' => $user->status,
                    'is_active' => $user->is_active,
                    'updated_at' => $user->updated_at,
                ],
            ],
        ]);
    }
}

