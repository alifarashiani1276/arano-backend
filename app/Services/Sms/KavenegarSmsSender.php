<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class KavenegarSmsSender implements SmsSender
{
    public function send(string $phone, string $code): void
    {
        $apiKey = config('services.sms.kavenegar.api_key');
        $template = config('services.sms.kavenegar.otp_template');

        if (empty($apiKey)) {
            throw new RuntimeException(
                'Kavenegar API key is not configured.'
            );
        }

        if (empty($template)) {
            throw new RuntimeException(
                'Kavenegar OTP template is not configured.'
            );
        }

        $url = sprintf(
            'https://api.kavenegar.com/v1/%s/verify/lookup.json',
            rawurlencode($apiKey)
        );

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->connectTimeout(5)
                ->post($url, [
                    'receptor' => $phone,
                    'token' => $code,
                    'template' => $template,
                ]);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'ارتباط با سرویس پیامکی برقرار نشد.',
                0,
                $e
            );
        }

        if (!$response->successful()) {
            throw new RuntimeException(
                'سرویس پیامکی درخواست را قبول نکرد.'
            );
        }

        $data = $response->json();

        /*
         * Kavenegar ممکن است HTTP 200 برگرداند،
         * ولی status داخلی API نشان‌دهنده خطا باشد.
         */
        $status = data_get($data, 'return.status');

        if ((int) $status !== 200) {
            $message = data_get(
                $data,
                'return.message',
                'خطا در ارسال پیامک.'
            );

            throw new RuntimeException(
                "Kavenegar error: {$message}"
            );
        }
    }
}