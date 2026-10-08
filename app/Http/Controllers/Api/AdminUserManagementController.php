<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserManagementController extends Controller
{
    /**
     * نمایش تمام کاربران و ادمین‌ها
     */
    public function index(): JsonResponse
    {
        $users = User::whereIn('role', ['USER', 'ADMIN'])
            ->latest()
            ->get([
                'id',
                'phone',
                'first_name',
                'last_name',
                'email',
                'bio',
                'role',
                'status',
                'is_active',
                'created_at',
                'updated_at',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $users,
            ],
        ]);
    }

    /**
     * نمایش جزئیات یک کاربر یا ادمین
     */
    public function show(int $id): JsonResponse
    {
        $user = User::whereIn('role', ['USER', 'ADMIN'])
            ->withCount([
                'projects',
                'conversations',
            ])
            ->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر مورد نظر یافت نشد.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
            ],
        ]);
    }

    /**
     * فعال / غیرفعال کردن حساب کاربر یا ادمین
     */
    public function toggleActive(
        Request $request,
        int $id
    ): JsonResponse {
        $currentAdmin = $request->user();

        $user = User::whereIn('role', ['USER', 'ADMIN'])
            ->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر مورد نظر یافت نشد.',
            ], 404);
        }

        // سوپر ادمین نباید از این مسیر مدیریت شود
        if ($user->role === 'SUPER_ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'حساب سوپر ادمین از این بخش قابل مدیریت نیست.',
            ], 403);
        }

        // جلوگیری از غیرفعال کردن حساب خود مدیر
        if ($user->id === $currentAdmin->id) {
            return response()->json([
                'success' => false,
                'message' => 'امکان تغییر وضعیت حساب خودتان وجود ندارد.',
            ], 403);
        }

        $user->update([
            'is_active' => !$user->is_active,
        ]);

        /*
         * اگر حساب غیرفعال شد،
         * تمام توکن‌های فعلی آن باطل می‌شوند.
         */
        if (!$user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => $user->is_active
                ? 'حساب کاربر فعال شد.'
                : 'حساب کاربر غیرفعال شد.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'phone' => $user->phone,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'is_active' => $user->is_active,
                ],
            ],
        ]);
    }

    /**
     * تغییر نقش بین USER و ADMIN
     *
     * فقط SUPER_ADMIN می‌تواند این عملیات را انجام دهد.
     */
    public function changeRole(
        Request $request,
        int $id
    ): JsonResponse {
        $currentAdmin = $request->user();

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'کاربر مورد نظر یافت نشد.',
            ], 404);
        }

        // سوپر ادمین نباید نقش خودش را تغییر دهد
        if ($user->id === $currentAdmin->id) {
            return response()->json([
                'success' => false,
                'message' => 'امکان تغییر نقش حساب خودتان وجود ندارد.',
            ], 403);
        }

        // SUPER_ADMIN قابل تبدیل نیست
        if ($user->role === 'SUPER_ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'نقش سوپر ادمین قابل تغییر نیست.',
            ], 403);
        }

        // فقط USER و ADMIN قابل تبدیل هستند
        if (!in_array($user->role, ['USER', 'ADMIN'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'نقش این حساب قابل تغییر نیست.',
            ], 422);
        }

        $oldRole = $user->role;

        $newRole = $oldRole === 'USER'
            ? 'ADMIN'
            : 'USER';

        /*
         * تغییر نقش یک تغییر مهم در سطح دسترسی است.
         *
         * بنابراین تمام توکن‌های قبلی باطل می‌شوند
         * تا کاربر مجبور شود با سطح دسترسی جدید
         * دوباره وارد سیستم شود.
         */
        $user->tokens()->delete();

        if ($newRole === 'ADMIN') {
            $user->update([
                'role' => 'ADMIN',
                'password' => null,
                'status' => 'PENDING',
            ]);
        } else {
            $user->update([
                'role' => 'USER',
                'password' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $newRole === 'ADMIN'
                ? 'کاربر با موفقیت به ادمین تبدیل شد.'
                : 'ادمین با موفقیت به کاربر عادی تبدیل شد.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'phone' => $user->phone,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'is_active' => $user->is_active,
                ],
            ],
        ]);
    }
}

