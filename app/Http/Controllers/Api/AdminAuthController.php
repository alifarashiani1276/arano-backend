<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminAuthController extends Controller
{
    /**
     * مدت اعتبار OTP Challenge برای Login
     */
    private const OTP_LOGIN_CHALLENGE_TTL = 5;

    /**
     * مدت اعتبار Setup Token
     */
    private const ADMIN_SETUP_TOKEN_TTL = 10;

    /**
     * مدت اعتبار Token کامل Admin
     */
    private const ADMIN_API_TOKEN_TTL = 8;

    /**
     * کلید Cache مربوط به OTP Login
     */
    private function otpChallengeKey(string $phone): string
    {
        return 'admin_login_otp_verified:' . $phone;
    }

    /**
     * کلید Lock مربوط به Login
     */
    private function loginLockKey(string $phone): string
    {
        return 'admin_login_lock:' . hash('sha256', $phone);
    }

    /**
     * ارسال OTP برای ورود مدیر
     */
    public function sendOtp(
        Request $request,
        OtpService $otpService
    ): JsonResponse {
        $validated = $request->validate([
            'phone' => [
                'required',
                'string',
                'regex:/^09\d{9}$/',
            ],
        ]);

        $phone = trim($validated['phone']);

        $admin = User::where('phone', $phone)
            ->whereIn('role', ['ADMIN', 'SUPER_ADMIN'])
            ->first();

        /*
         * اطلاعات حساب Admin را بیش از حد افشا نمی‌کنیم.
         */
        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'اطلاعات ورود مدیر صحیح نیست.',
            ], 403);
        }

        if (!$admin->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'اطلاعات ورود مدیر صحیح نیست.',
            ], 403);
        }

        /*
         * اگر Challenge قبلی وجود داشته باشد،
         * با ارسال OTP جدید باطل می‌شود.
         */
        Cache::forget(
            $this->otpChallengeKey($admin->phone)
        );

        /*
         * OTP جدید تولید می‌شود.
         */
        $otpService->send($admin->phone);

        return response()->json([
            'success' => true,
            'message' => 'کد تأیید با موفقیت ارسال شد.',
        ]);
    }

    /**
     * تأیید OTP
     */
    public function verifyOtp(
        Request $request,
        OtpService $otpService
    ): JsonResponse {
        $validated = $request->validate([
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

        $phone = trim($validated['phone']);
        $code = (string) $validated['code'];

        $admin = User::where('phone', $phone)
            ->whereIn('role', ['ADMIN', 'SUPER_ADMIN'])
            ->first();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'اطلاعات ورود مدیر صحیح نیست.',
            ], 403);
        }

        if (!$admin->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'اطلاعات ورود مدیر صحیح نیست.',
            ], 403);
        }

        /*
         * OTP باید یک بار و به صورت امن مصرف شود.
         * Atomic بودن مصرف خود OTP در OtpService پیاده‌سازی شده است.
         */
        $verified = $otpService->verify(
            $admin->phone,
            $code
        );

        if (!$verified) {
            return response()->json([
                'success' => false,
                'message' => 'کد تأیید نامعتبر یا منقضی شده است.',
            ], 422);
        }

        /*
         * بررسی کامل بودن پروفایل و Password
         */
        $profileCompleted =
            !empty($admin->first_name) &&
            !empty($admin->last_name) &&
            !empty($admin->email) &&
            !empty($admin->password);

        /*
         * Admin جدید:
         * فقط Setup Token دریافت می‌کند.
         */
        if (!$profileCompleted || $admin->status === 'PENDING') {

            /*
             * همه Setup Tokenهای قبلی این Admin را حذف می‌کنیم.
             */
            $admin->tokens()
                ->where('name', 'admin-setup-token')
                ->delete();

            /*
             * Setup Token کوتاه‌عمر صادر می‌شود.
             */
            $setupToken = $admin
                ->createToken(
                    'admin-setup-token',
                    ['admin:setup'],
                    now()->addMinutes(self::ADMIN_SETUP_TOKEN_TTL)
                )
                ->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'شماره تلفن تأیید شد. اکنون پروفایل مدیر را تکمیل کنید.',
                'data' => [
                    'setup_required' => true,
                    'phone_verified' => true,
                    'phone' => $admin->phone,
                    'token' => $setupToken,
                    'token_expires_in' => self::ADMIN_SETUP_TOKEN_TTL * 60,
                ],
            ]);
        }

        /*
         * Admin موجود و تأییدشده:
         *
         * OTP برای Login تأیید شده است.
         *
         * این Challenge فقط 5 دقیقه اعتبار دارد.
         */
        Cache::put(
            $this->otpChallengeKey($admin->phone),
            [
                'admin_id' => $admin->id,
                'phone' => $admin->phone,
                'verified_at' => now()->toIso8601String(),
            ],
            now()->addMinutes(self::OTP_LOGIN_CHALLENGE_TTL)
        );

        return response()->json([
            'success' => true,
            'message' => 'کد تأیید شد. اکنون رمز عبور مدیر را وارد کنید.',
            'data' => [
                'setup_required' => false,
                'phone_verified' => true,
                'password_required' => true,
                'phone' => $admin->phone,
                'challenge_expires_in' => self::OTP_LOGIN_CHALLENGE_TTL * 60,
            ],
        ]);
    }

    /**
     * تکمیل اولیه پروفایل Admin جدید
     */
    public function completeProfile(
        Request $request
    ): JsonResponse {
        $admin = $request->user();

        if (!$admin || $admin->role !== 'ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'دسترسی غیرمجاز است.',
            ], 403);
        }

        if ($admin->status !== 'PENDING') {
            return response()->json([
                'success' => false,
                'message' => 'این حساب قبلاً تکمیل شده است.',
            ], 403);
        }

        $validated = $request->validate([
            'first_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[\p{Arabic}A-Za-z\s\x{200C}]+$/u',
            ],

            'last_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[\p{Arabic}A-Za-z\s\x{200C}]+$/u',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($admin->id),
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
                'confirmed',
            ],

            'bio' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $admin->update([
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'email' => strtolower(trim($validated['email'])),
            'password' => Hash::make($validated['password']),
            'bio' => isset($validated['bio'])
                ? trim($validated['bio'])
                : null,
            'status' => 'APPROVED',
        ]);

        /*
         * همه Setup Tokenهای این Admin باید باطل شوند.
         */
        $admin->tokens()
            ->where('name', 'admin-setup-token')
            ->delete();

        /*
         * حذف Challenge احتمالی Login
         */
        Cache::forget(
            $this->otpChallengeKey($admin->phone)
        );

        /*
         * صدور Token کامل Admin با عمر محدود
         */
        $token = $admin
            ->createToken(
                'admin-api-token',
                ['admin'],
                now()->addHours(self::ADMIN_API_TOKEN_TTL)
            )
            ->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'پروفایل مدیر با موفقیت تکمیل شد.',
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
                ],
                'token' => $token,
                'token_expires_in' => self::ADMIN_API_TOKEN_TTL * 60 * 60,
            ],
        ]);
    }

    /**
     * ورود نهایی مدیر
     */
    public function login(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'phone' => [
                'required',
                'string',
                'regex:/^09\d{9}$/',
            ],

            'password' => [
                'required',
                'string',
            ],
        ]);

        $phone = trim($validated['phone']);
        $password = $validated['password'];

        $admin = User::where('phone', $phone)
            ->whereIn('role', ['ADMIN', 'SUPER_ADMIN'])
            ->first();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'اطلاعات ورود مدیر صحیح نیست.',
            ], 401);
        }

        if (!$admin->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'اطلاعات ورود مدیر صحیح نیست.',
            ], 403);
        }

        if ($admin->status !== 'APPROVED') {
            return response()->json([
                'success' => false,
                'message' => 'ابتدا باید پروفایل مدیر تکمیل شود.',
            ], 403);
        }

        if (!$admin->password) {
            return response()->json([
                'success' => false,
                'message' => 'برای این حساب هنوز رمز عبور تعیین نشده است.',
            ], 403);
        }

        /*
         * برای جلوگیری از Race Condition در مصرف Login Challenge،
         * کل مرحله بررسی Challenge + Password داخل Atomic Lock انجام می‌شود.
         */
        return Cache::lock(
            $this->loginLockKey($admin->phone),
            10
        )->block(3, function () use ($admin, $password) {

            $challengeKey = $this->otpChallengeKey($admin->phone);

            /*
             * Challenge مربوط به همین Admin
             */
            $challenge = Cache::get($challengeKey);

            if (!$challenge) {
                return response()->json([
                    'success' => false,
                    'message' => 'ابتدا باید کد تأیید تلفن را تأیید کنید.',
                    'data' => [
                        'otp_required' => true,
                    ],
                ], 403);
            }

            /*
             * اطمینان از اینکه Challenge متعلق به همین Admin است.
             */
            if (
                !isset($challenge['admin_id']) ||
                (int) $challenge['admin_id'] !== (int) $admin->id
            ) {
                Cache::forget($challengeKey);

                return response()->json([
                    'success' => false,
                    'message' => 'درخواست احراز هویت معتبر نیست.',
                ], 403);
            }

            /*
             * بررسی Password
             */
            if (!Hash::check($password, $admin->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'رمز عبور صحیح نیست.',
                ], 401);
            }

            /*
             * Challenge فقط یک بار مصرف است.
             */
            Cache::forget($challengeKey);

            /*
             * صدور Token کامل Admin با عمر محدود
             */
            $token = $admin
                ->createToken(
                    'admin-api-token',
                    ['admin'],
                    now()->addHours(self::ADMIN_API_TOKEN_TTL)
                )
                ->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'ورود مدیر با موفقیت انجام شد.',
                'data' => [
                    'admin' => [
                        'id' => $admin->id,
                        'phone' => $admin->phone,
                        'first_name' => $admin->first_name,
                        'last_name' => $admin->last_name,
                        'email' => $admin->email,
                        'role' => $admin->role,
                        'status' => $admin->status,
                    ],
                    'token' => $token,
                    'token_expires_in' => self::ADMIN_API_TOKEN_TTL * 60 * 60,
                ],
            ]);
        });
    }

    /**
     * اطلاعات مدیر لاگین‌شده
     */
    public function me(Request $request): JsonResponse
    {
        $admin = $request->user();

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
                ],
            ],
        ]);
    }

    /**
     * خروج مدیر
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'مدیر با موفقیت از حساب خارج شد.',
        ]);
    }
}