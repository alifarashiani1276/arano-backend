<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function __construct(
        private readonly SmsSender $smsSender
    ) {
    }

    /**
     * ایجاد OTP جدید
     *
     * این متد برای Tinker و سایر مصرف‌کننده‌های داخلی
     * همچنان کد OTP را برمی‌گرداند.
     */
    public function generate(string $phone): string
    {
        OtpCode::where('phone', $phone)
            ->whereNull('used_at')
            ->update([
                'used_at' => now(),
            ]);

        $code = (string) random_int(100000, 999999);

        OtpCode::create([
            'phone' => $phone,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(2),
        ]);

        /*
         * فقط در محیط توسعه کد OTP را Log می‌کنیم.
         * در Production هرگز OTP خام داخل Log قرار نمی‌گیرد.
         */
        if (!app()->environment('production')) {
            Log::info('Development OTP generated.', [
                'phone' => $phone,
                'code' => $code,
            ]);
        }

        return $code;
    }

    /**
     * ایجاد و ارسال OTP
     */
    public function send(string $phone): void
    {
        $code = $this->generate($phone);

        $this->smsSender->send(
            $phone,
            $code
        );
    }

    /**
     * بررسی و مصرف اتمیک OTP
     */
    public function verify(string $phone, string $code): bool
    {
        return DB::transaction(function () use ($phone, $code) {
            $otp = OtpCode::where('phone', $phone)
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