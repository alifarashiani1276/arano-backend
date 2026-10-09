<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $code): void
    {
        if (!app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'Log SMS driver is allowed only in local/testing environments.'
            );
        }

        Log::info('Development OTP generated.', [
            'phone' => $phone,
            'code' => $code,
        ]);
    }
}