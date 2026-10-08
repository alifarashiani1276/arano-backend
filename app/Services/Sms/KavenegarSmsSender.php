<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class KavenegarSmsSender implements SmsSender
{
    public function send(string $phone, string $code): void
    {
        $apiKey = config('services.sms.kavenegar.api_key');
        $template = config('services.sms.kavenegar.otp_template');

        if (empty($apiKey)) {
            throw new RuntimeException('Kavenegar API key is not configured.');
        }

        if (empty($template)) {
            throw new RuntimeException('Kavenegar OTP template is not configured.');
        }

        $url = sprintf(
            'https://api.kavenegar.com/v1/%s/verify/lookup.json',
            rawurlencode($apiKey)
        );

        try {
           $response = Http::asForm()
               ->withOptions(['verify' => !app()->environment('local')])
                ->timeout(10)
                ->connectTimeout(5)
                ->post($url, [
                    'receptor' => $phone,
                    'token' => $code,
                    'template' => $template,
                ]);
        } catch (\Throwable $e) {
            Log::error('Kavenegar connection failed', ['error' => $e->getMessage()]);

            throw new RuntimeException('ارتباط با سرویس پیامکی برقرار نشد.', 0, $e);
        }

        $data = $response->json();
        $status = (int) data_get($data, 'return.status');

        if (!$response->successful() || $status !== 200) {
            Log::error('Kavenegar send failed', [
                'http_status' => $response->status(),
                'kavenegar_status' => $status,
                'message' => data_get($data, 'return.message'),
            ]);

            throw new RuntimeException(
                'Kavenegar error ' . $status . ': ' . data_get($data, 'return.message', 'unknown')
            );
        }
    }
}