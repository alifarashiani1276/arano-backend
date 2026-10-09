<?php

namespace App\Providers;

use App\Services\Sms\KavenegarSmsSender;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * اتصال SmsSender به Driver انتخاب‌شده در Config
         */
        $this->app->singleton(SmsSender::class, function () {
            $driver = config('services.sms.driver', 'log');

            return match ($driver) {
                'log' => app(LogSmsSender::class),
                'kavenegar' => app(KavenegarSmsSender::class),

                default => throw new RuntimeException(
                    "SMS driver [{$driver}] پشتیبانی نمی‌شود."
                ),
            };
        });
    }

    public function boot(): void
    {
        /*
         * User OTP Send
         */
        RateLimiter::for('user-send-otp', function (Request $request) {
            $phone = trim((string) $request->input('phone'));

            return Limit::perMinutes(10, 5)
                ->by($phone . '|' . $request->ip());
        });

        /*
         * Admin OTP Send
         */
        RateLimiter::for('admin-send-otp', function (Request $request) {
            $phone = trim((string) $request->input('phone'));

            return Limit::perMinutes(10, 5)
                ->by($phone . '|' . $request->ip());
        });
        // محدودیت ارسال OTP برای بازیابی رمز مدیر
        RateLimiter::for('admin-password-reset-send', function (Request $request) {
            $phone = trim((string) $request->input('phone'));

            return [
                Limit::perMinutes(10, 3)
                    ->by($phone . '|' . $request->ip()),

                Limit::perMinutes(10, 20)
                    ->by($request->ip()),
            ];
        });

// محدودیت تأیید OTP بازیابی رمز مدیر
        RateLimiter::for('admin-password-reset-verify', function (Request $request) {
            $phone = trim((string) $request->input('phone'));

            return [
                Limit::perMinutes(10, 5)
                    ->by($phone . '|' . $request->ip()),

                Limit::perMinutes(10, 20)
                    ->by($request->ip()),
            ];
        });

// محدودیت ثبت رمز جدید
        RateLimiter::for('admin-password-reset-reset', function (Request $request) {
            $adminId = $request->user()?->id ?? 'guest';

            return [
                Limit::perMinutes(10, 5)
                    ->by($adminId . '|' . $request->ip()),

                Limit::perMinutes(10, 20)
                    ->by($request->ip()),
            ];
        });
        /*
         * Admin OTP Verify
         */
        RateLimiter::for('admin-verify-otp', function (Request $request) {
            $phone = trim((string) $request->input('phone'));

            return Limit::perMinutes(10, 5)
                ->by($phone . '|' . $request->ip());
        });

        /*
         * User OTP Verify
         */
        RateLimiter::for('user-verify-otp', function (Request $request) {
            $phone = trim((string) $request->input('phone'));

            return Limit::perMinutes(10, 5)
                ->by($phone . '|' . $request->ip());
        });

        /*
         * Public Consultation
         *
         * هر شماره: حداکثر 3 درخواست در 10 دقیقه
         * هر IP: حداکثر 20 درخواست در 10 دقیقه
         */
        RateLimiter::for('consultation-submit', function (Request $request) {
            $phone = trim((string) $request->input('phone'));

            return [
                Limit::perMinutes(10, 3)
                    ->by('phone:' . $phone),

                Limit::perMinutes(10, 20)
                    ->by('ip:' . $request->ip()),
            ];
        });

        /*
         * Admin Login
         *
         * هر IP: حداکثر 20 تلاش در 10 دقیقه
         * هر شماره + IP: حداکثر 5 تلاش در 10 دقیقه
         */
        RateLimiter::for('admin-login', function (Request $request) {
            $phone = trim((string) $request->input('phone'));

            return [
                Limit::perMinutes(10, 20)
                    ->by('ip:' . $request->ip()),

                Limit::perMinutes(10, 5)
                    ->by('phone:' . $phone . '|ip:' . $request->ip()),
            ];
        });
    }
}