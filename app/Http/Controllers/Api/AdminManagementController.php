<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminManagementController extends Controller
{
    /**
     * بررسی دسترسی Super Admin
     */
    private function ensureSuperAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'SUPER_ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'فقط Super Admin به مدیریت مدیران دسترسی دارد.',
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'حساب Super Admin غیرفعال است.',
            ], 403);
        }

        return null;
    }

    /**
     * لیست تمام مدیران
     */
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->ensureSuperAdmin($request)) {
            return $response;
        }

        $admins = User::whereIn('role', ['ADMIN', 'SUPER_ADMIN'])
            ->withCount([
                'projects',
                'conversations',
            ])
            ->latest('id')
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
                'admins' => $admins,
            ],
        ]);
    }

    /**
     * نمایش اطلاعات یک مدیر
     */
    public function show(
        Request $request,
        int $id
    ): JsonResponse {
        if ($response = $this->ensureSuperAdmin($request)) {
            return $response;
        }

        $admin = User::whereIn('role', ['ADMIN', 'SUPER_ADMIN'])
            ->withCount([
                'projects',
                'conversations',
            ])
            ->find($id);

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'مدیر مورد نظر یافت نشد.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'phone' => $admin->phone,
                    'first_name' => $admin->first_name,
                    'last_name' => $admin->last_name,
                    'email' => $admin->email,
                    'bio' => $admin->bio,
                    'role' => $admin->role,
                    'status' => $admin->status,
                    'is_active' => $admin->is_active,
                    'projects_count' => $admin->projects_count,
                    'conversations_count' => $admin->conversations_count,
                    'created_at' => $admin->created_at,
                    'updated_at' => $admin->updated_at,
                ],
            ],
        ]);
    }

    /**
     * ثبت مدیر جدید
     *
     * نکته مهم:
     * Super Admin فقط شماره تلفن مدیر جدید را ثبت می‌کند.
     * مدیر جدید بعداً با OTP وارد مرحله تکمیل حساب می‌شود.
     */
    public function store(Request $request): JsonResponse
    {
        if ($response = $this->ensureSuperAdmin($request)) {
            return $response;
        }

        $validator = Validator::make(
            $request->all(),
            [
                'phone' => [
                    'required',
                    'string',
                    'max:20',
                    'unique:users,phone',
                ],
            ],
            [
                'phone.required' => 'شماره تلفن الزامی است.',
                'phone.string' => 'شماره تلفن باید به صورت رشته ارسال شود.',
                'phone.max' => 'شماره تلفن نمی‌تواند بیشتر از ۲۰ کاراکتر باشد.',
                'phone.unique' => 'این شماره تلفن قبلاً در سیستم ثبت شده است.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'اطلاعات وارد شده معتبر نیست.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $admin = User::create([
            'phone' => $validator->validated()['phone'],
            'password' => null,
            'first_name' => null,
            'last_name' => null,
            'email' => null,
            'bio' => null,
            'role' => 'ADMIN',
            'status' => 'PENDING',
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'شماره مدیر جدید با موفقیت ثبت شد. مدیر جدید اکنون می‌تواند با OTP وارد مرحله تکمیل حساب شود.',
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'phone' => $admin->phone,
                    'role' => $admin->role,
                    'status' => $admin->status,
                    'is_active' => $admin->is_active,
                    'profile_completed' => false,
                ],
            ],
        ], 201);
    }

    /**
     * فعال / غیرفعال کردن مدیر معمولی
     *
     * Super Admin خودش را نمی‌تواند از این قسمت غیرفعال کند.
     */
    public function toggleActive(
        Request $request,
        int $id
    ): JsonResponse {
        if ($response = $this->ensureSuperAdmin($request)) {
            return $response;
        }

        $admin = User::where('role', 'ADMIN')->find($id);

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'مدیر مورد نظر یافت نشد.',
            ], 404);
        }

        $admin->is_active = !$admin->is_active;
        $admin->save();

        return response()->json([
            'success' => true,
            'message' => $admin->is_active
                ? 'حساب مدیر فعال شد.'
                : 'حساب مدیر غیرفعال شد.',
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'phone' => $admin->phone,
                    'role' => $admin->role,
                    'status' => $admin->status,
                    'is_active' => $admin->is_active,
                ],
            ],
        ]);
    }
}