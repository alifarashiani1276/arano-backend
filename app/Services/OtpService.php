<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class OtpService
{
    public const PURPOSE_USER_AUTH = 'user_auth';
    public const PURPOSE_ADMIN_AUTH = 'admin_auth';
    public const PURPOSE_ADMIN_PASSWORD_RESET = 'admin_password_reset';

    public function __construct(
        private readonly SmsSender $smsSender
    ) {
    }

    /**
     * بررسی معتبر بودن کاربرد OTP
     */
    private function validatePurpose(string $purpose): void
    {
        $allowedPurposes = [
            self::PURPOSE_USER_AUTH,
            self::PURPOSE_ADMIN_AUTH,
            self::PURPOSE_ADMIN_PASSWORD_RESET,
        ];

        if (!in_array($purpose, $allowedPurposes, true)) {
            throw new InvalidArgumentException(
                'Invalid OTP purpose.'
            );
        }
    }

    /**
     * ایجاد OTP جدید
     */
    public function generate(
        string $phone,
        string $purpose = self::PURPOSE_USER_AUTH
    ): string {
        $this->validatePurpose($purpose);

        // فقط OTPهای استفاده‌نشده همین کاربرد باطل می‌شوند.
        OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update([
                'used_at' => now(),
            ]);

        $code = (string) random_int(100000, 999999);

        OtpCode::create([
            'phone' => $phone,
            'purpose' => $purpose,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(2),
        ]);

        // ثبت OTP خام فقط در محیط توسعه
        if (app()->environment(['local', 'testing'])) {
            Log::info('Development OTP generated.', [
                'phone' => $phone,
                'purpose' => $purpose,
                'code' => $code,
            ]);
        }

        return $code;
    }

    /**
     * ایجاد و ارسال OTP
     */
    public function send(
        string $phone,
        string $purpose = self::PURPOSE_USER_AUTH
    ): void {
        $code = $this->generate($phone, $purpose);

        $this->smsSender->send($phone, $code);
    }

    /**
     * بررسی و مصرف اتمیک OTP
     */
    public function verify(
        string $phone,
        string $code,
        string $purpose = self::PURPOSE_USER_AUTH
    ): bool {
        $this->validatePurpose($purpose);

        return DB::transaction(function () use (
            $phone,
            $code,
            $purpose
        ) {
            $otp = OtpCode::where('phone', $phone)
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (!$otp) {
                return false;
            }

            if ($otp->expires_at->isPast()) {
                return false;
            }

            if (!Hash::check($code, $otp->code)) {
                return false;
            }

            $otp->update([
                'used_at' => now(),
            ]);

            return true;
        });
    }
}
