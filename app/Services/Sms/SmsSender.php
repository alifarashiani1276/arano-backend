<?php

namespace App\Services\Sms;

interface SmsSender
{
    /**
     * ارسال کد OTP
     */
    public function send(string $phone, string $code): void;
}