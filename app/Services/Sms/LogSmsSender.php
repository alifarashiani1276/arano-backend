<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class LogSmsSender implements SmsSender
{
    /**
     * Driver مخصوص محیط توسعه
     *
     * در Production عمداً نباید استفاده شود.
     */
    public function send(string $phone, string $code): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'Log SMS driver is not allowed in production.'
            );
        }

        Log::info('Development OTP generated.', [
            'phone' => $phone,
            'code' => $code,
        ]);
    }
}