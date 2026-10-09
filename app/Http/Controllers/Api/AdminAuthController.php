<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminAuthController extends Controller
{
    private const OTP_LOGIN_CHALLENGE_TTL = 5;
    private const ADMIN_SETUP_TOKEN_TTL = 10;
    private const ADMIN_API_TOKEN_TTL = 8;
    private const ADMIN_PASSWORD_RESET_TOKEN_TTL = 5;

    private function otpChallengeKey(string $phone): string
    {
        return 'admin_login_otp_verified:' . $phone;
    }

    private function loginLockKey(string $phone): string
    {
        return 'admin_login_lock:' . hash('sha256', $phone);
    }

    /**
     * بررسی شرایط حساب برای بازیابی رمز عبور
     */
    private function isEligibleForPasswordReset(User $admin): bool
    {
        return in_array(
            $admin->role,
            ['ADMIN', 'SUPER_ADMIN'],
            true
        )
            && (bool) $admin->is_active
            && $admin->status === 'APPROVED'
            && filled($admin->first_name)
            && filled($admin->last_name)
            && filled($admin->email)
            && filled($admin->password);
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

        if (!$admin || !$admin->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'اطلاعات ورود مدیر صحیح نیست.',
            ], 403);
        }

        Cache::forget(
            $this->otpChallengeKey($admin->phone)
        );

        $otpService->send(
            $admin->phone,
            OtpService::PURPOSE_ADMIN_AUTH
        );

        return response()->json([
            'success' => true,
            'message' => 'کد تأیید با موفقیت ارسال شد.',
        ]);
    }

    /**
     * تأیید OTP ورود مدیر
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

        if (!$admin || !$admin->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'اطلاعات ورود مدیر صحیح نیست.',
            ], 403);
        }

        $verified = $otpService->verify(
            $admin->phone,
            $code,
            OtpService::PURPOSE_ADMIN_AUTH
        );

        if (!$verified) {
            return response()->json([
                'success' => false,
                'message' => 'کد تأیید نامعتبر یا منقضی شده است.',
            ], 422);
        }

        $profileCompleted =
            !empty($admin->first_name) &&
            !empty($admin->last_name) &&
            !empty($admin->email) &&
            !empty($admin->password);

        if (
            !$profileCompleted ||
            $admin->status === 'PENDING'
        ) {
            $admin->tokens()
                ->where('name', 'admin-setup-token')
                ->delete();

            $setupToken = $admin->createToken(
                'admin-setup-token',
                ['admin:setup'],
                now()->addMinutes(self::ADMIN_SETUP_TOKEN_TTL)
            )->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'شماره تلفن تأیید شد. اکنون پروفایل مدیر را تکمیل کنید.',
                'data' => [
                    'setup_required' => true,
                    'phone_verified' => true,
                    'phone' => $admin->phone,
                    'token' => $setupToken,
                    'token_expires_in' =>
                        self::ADMIN_SETUP_TOKEN_TTL * 60,
                ],
            ]);
        }

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
                'challenge_expires_in' =>
                    self::OTP_LOGIN_CHALLENGE_TTL * 60,
            ],
        ]);
    }

    /**
     * مرحله اول بازیابی رمز: ارسال OTP
     */
    public function sendPasswordResetOtp(
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
        $admin = User::where('phone', $phone)->first();

        /*
         * پاسخ برای شماره‌های مختلف یکسان است تا وجود حساب افشا نشود.
         */
        if ($admin && $this->isEligibleForPasswordReset($admin)) {
            try {
                /*
                 * توکن بازیابی قبلی با شروع درخواست جدید باطل می‌شود.
                 */
                $admin->tokens()
                    ->where('name', 'admin-password-reset-token')
                    ->delete();

                $otpService->send(
                    $admin->phone,
                    OtpService::PURPOSE_ADMIN_PASSWORD_RESET
                );
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'اگر شماره واردشده واجد شرایط بازیابی باشد، فرایند ارسال کد انجام می‌شود.',
        ]);
    }

    /**
     * مرحله دوم بازیابی رمز: تأیید OTP و صدور توکن محدود
     */
    public function verifyPasswordResetOtp(
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

        $admin = User::where('phone', $phone)->first();

        if (
            !$admin ||
            !$this->isEligibleForPasswordReset($admin)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'کد تأیید نامعتبر یا منقضی شده است.',
            ], 422);
        }

        /*
         * بررسی مجدد حساب، مصرف OTP و صدور توکن در Transaction.
         */
        $resetToken = DB::transaction(function () use (
            $admin,
            $code,
            $otpService
        ) {
            $lockedAdmin = User::query()
                ->whereKey($admin->id)
                ->lockForUpdate()
                ->first();

            if (
                !$lockedAdmin ||
                !$this->isEligibleForPasswordReset($lockedAdmin)
            ) {
                return null;
            }

            $verified = $otpService->verify(
                $lockedAdmin->phone,
                $code,
                OtpService::PURPOSE_ADMIN_PASSWORD_RESET
            );

            if (!$verified) {
                return null;
            }

            $lockedAdmin->tokens()
                ->where('name', 'admin-password-reset-token')
                ->delete();

            return $lockedAdmin->createToken(
                'admin-password-reset-token',
                ['admin:password-reset'],
                now()->addMinutes(
                    self::ADMIN_PASSWORD_RESET_TOKEN_TTL
                )
            )->plainTextToken;
        }, 3);

        if (!$resetToken) {
            return response()->json([
                'success' => false,
                'message' => 'کد تأیید نامعتبر یا منقضی شده است.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'کد تأیید شد. اکنون رمز جدید را ثبت کنید.',
            'data' => [
                'reset_token' => $resetToken,
                'token_type' => 'Bearer',
                'expires_in' =>
                    self::ADMIN_PASSWORD_RESET_TOKEN_TTL * 60,
            ],
        ]);
    }

    /**
     * مرحله سوم بازیابی رمز: ثبت رمز جدید
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $admin = $request->user();

        if (
            !$admin ||
            !$this->isEligibleForPasswordReset($admin)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'دسترسی غیرمجاز است.',
            ], 403);
        }

        $accessToken = $admin->currentAccessToken();

        if (
            !$accessToken ||
            !isset($accessToken->name) ||
            $accessToken->name !== 'admin-password-reset-token' ||
            !$accessToken->can('admin:password-reset')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'توکن بازیابی معتبر نیست.',
            ], 403);
        }

        $validated = $request->validate([
            'password' => [
                'required',
                'string',
                'min:8',
                'max:128',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
                'confirmed',
            ],
            'password_confirmation' => [
                'required',
                'string',
                'max:128',
            ],
        ]);

        $adminId = $admin->id;
        $phone = $admin->phone;
        $tokenId = $accessToken->getKey();
        $newPassword = $validated['password'];

        try {
            /*
             * همان قفل را ورود عادی مدیر هم استفاده می‌کند.
             */
            $resetCompleted = Cache::lock(
                $this->loginLockKey($phone),
                30
            )->block(3, function () use (
                $adminId,
                $phone,
                $tokenId,
                $newPassword
            ) {
                $completed = DB::transaction(function () use (
                    $adminId,
                    $tokenId,
                    $newPassword
                ): bool {
                    /*
                     * قفل ادمین قبل از قفل توکن گرفته می‌شود.
                     */
                    $lockedAdmin = User::query()
                        ->whereKey($adminId)
                        ->lockForUpdate()
                        ->first();

                    if (
                        !$lockedAdmin ||
                        !$this->isEligibleForPasswordReset($lockedAdmin)
                    ) {
                        return false;
                    }

                    /*
                     * توکن باید همچنان معتبر و متعلق به همین ادمین باشد.
                     */
                    $storedToken = $lockedAdmin->tokens()
                        ->whereKey($tokenId)
                        ->lockForUpdate()
                        ->first();

                    if (
                        !$storedToken ||
                        $storedToken->name !== 'admin-password-reset-token' ||
                        !$storedToken->can('admin:password-reset') ||
                        (
                            $storedToken->expires_at &&
                            $storedToken->expires_at->isPast()
                        )
                    ) {
                        return false;
                    }

                    $lockedAdmin->password = Hash::make($newPassword);
                    $lockedAdmin->save();

                    /*
                     * باطل‌کردن همه OTPهای استفاده‌نشده این شماره
                     */
                    OtpCode::where('phone', $lockedAdmin->phone)
                        ->whereNull('used_at')
                        ->update([
                            'used_at' => now(),
                        ]);

                    /*
                     * تمام توکن‌های ادمین باطل می‌شوند.
                     */
                    $lockedAdmin->tokens()->delete();

                    return true;
                }, 3);

                /*
                 * Challenge قبل از آزادشدن قفل حذف می‌شود.
                 */
                if ($completed) {
                    Cache::forget(
                        $this->otpChallengeKey($phone)
                    );
                }

                return $completed;
            });
        } catch (LockTimeoutException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'درخواست دیگری در حال پردازش است. لطفاً دوباره تلاش کنید.',
            ], 409);
        }

        if (!$resetCompleted) {
            return response()->json([
                'success' => false,
                'message' => 'توکن بازیابی معتبر نیست یا قبلاً استفاده شده است.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'رمز عبور با موفقیت تغییر کرد. اکنون با رمز جدید وارد شوید.',
        ]);
    }

    /**
     * تکمیل اولیه پروفایل Admin جدید
     */
    public function completeProfile(Request $request): JsonResponse
    {
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

        $admin->tokens()
            ->where('name', 'admin-setup-token')
            ->delete();

        Cache::forget(
            $this->otpChallengeKey($admin->phone)
        );

        $token = $admin->createToken(
            'admin-api-token',
            ['admin'],
            now()->addHours(self::ADMIN_API_TOKEN_TTL)
        )->plainTextToken;

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
                'token_expires_in' =>
                    self::ADMIN_API_TOKEN_TTL * 60 * 60,
            ],
        ]);
    }

    /**
     * ورود نهایی مدیر
     */
    public function login(Request $request): JsonResponse
    {
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

        try {
            /*
             * خواندن مجدد حساب و بررسی رمز داخل همان قفلی
             * انجام می‌شود که بازیابی رمز استفاده می‌کند.
             */
            return Cache::lock(
                $this->loginLockKey($phone),
                30
            )->block(3, function () use (
                $phone,
                $password
            ) {
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

                $challengeKey = $this->otpChallengeKey(
                    $admin->phone
                );

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
                 * رمز از رکورد تازه خوانده‌شده بررسی می‌شود.
                 */
                if (!Hash::check($password, $admin->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'رمز عبور صحیح نیست.',
                    ], 401);
                }

                Cache::forget($challengeKey);

                $token = $admin->createToken(
                    'admin-api-token',
                    ['admin'],
                    now()->addHours(self::ADMIN_API_TOKEN_TTL)
                )->plainTextToken;

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
                        'token_expires_in' =>
                            self::ADMIN_API_TOKEN_TTL * 60 * 60,
                    ],
                ]);
            });
        } catch (LockTimeoutException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'درخواست دیگری در حال پردازش است. لطفاً دوباره تلاش کنید.',
            ], 409);
        }
    }

    /**
     * اطلاعات مدیر واردشده
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