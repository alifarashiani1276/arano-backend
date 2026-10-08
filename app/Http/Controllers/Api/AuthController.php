<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * ارسال کد OTP
     */
    public function sendOtp(
        Request $request,
        OtpService $otpService
    ): JsonResponse {
        $request->validate([
            'phone' => [
                'required',
                'string',
                'regex:/^09\d{9}$/',
            ],
        ]);

        /*
         * فعلاً همان generate استفاده می‌شود.
         * بعد از بررسی OtpService آن را به send() متصل می‌کنیم.
         */
        $otpService->send($request->phone);

        return response()->json([
            'success' => true,
            'message' => 'کد تأیید با موفقیت ارسال شد.',
        ]);
    }

    /**
     * تأیید کد OTP و ورود کاربر
     */
    public function verifyOtp(
        Request $request,
        OtpService $otpService
    ): JsonResponse {
        $request->validate([
            'phone' => [
                'required',
                'string',
                'regex:/^09\d{9}$/',
            ],
            'code' => [
                'required',
                'digits:6',
            ],
        ]);

        $verified = $otpService->verify(
            $request->phone,
            $request->code
        );

        if (!$verified) {
            return response()->json([
                'success' => false,
                'message' => 'کد تأیید نامعتبر یا منقضی شده است.',
            ], 422);
        }

        $user = User::where('phone', $request->phone)->first();

        /*
         * Admin و Super Admin نباید از مسیر ورود کاربران وارد شوند.
         */
        if (
            $user &&
            in_array(
                $user->role,
                ['ADMIN', 'SUPER_ADMIN'],
                true
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'ورود مدیر از این مسیر مجاز نیست.',
            ], 403);
        }

        /*
         * اگر حسابی با این شماره وجود نداشته باشد،
         * یک User معمولی ایجاد می‌شود.
         */
        if (!$user) {
            $user = User::create([
                'phone' => $request->phone,
                'role' => 'USER',
                'status' => 'PENDING',
                'is_active' => true,
            ]);
        }

        /*
         * بررسی فعال بودن حساب
         */
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'حساب کاربری شما غیرفعال است.',
            ], 403);
        }

        /*
         * کاربر Reject شده اجازه ورود ندارد.
         */
        if ($user->status === 'REJECTED') {
            return response()->json([
                'success' => false,
                'message' => 'حساب کاربری شما توسط مدیر تأیید نشده است.',
            ], 403);
        }

        /*
         * ساخت Token با Ability محدود به User
         */
        $token = $user
            ->createToken(
                'user-api-token',
                ['user']
            )
            ->plainTextToken;

        /*
         * بررسی تکمیل بودن پروفایل
         */
        $profileCompleted =
            !empty($user->first_name) &&
            !empty($user->last_name) &&
            !empty($user->email);

        return response()->json([
            'success' => true,
            'message' => 'ورود با موفقیت انجام شد.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'phone' => $user->phone,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'profile_completed' => $profileCompleted,
                ],
                'token' => $token,
            ],
        ]);
    }

    /**
     * دریافت اطلاعات کاربر لاگین‌شده
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'phone' => $user->phone,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'bio' => $user->bio,
                    'role' => $user->role,
                    'status' => $user->status,
                    'profile_completed' =>
                        !empty($user->first_name) &&
                        !empty($user->last_name) &&
                        !empty($user->email),
                ],
            ],
        ]);
    }

    /**
     * خروج کاربر و حذف توکن فعلی
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'با موفقیت از حساب کاربری خارج شدید.',
        ]);
    }
}