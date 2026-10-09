<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('services.super_admin.password');

        if (empty($password)) {
            throw new RuntimeException(
                'SUPER_ADMIN_PASSWORD در فایل .env تنظیم نشده است.'
            );
        }

        $phone = config('services.super_admin.phone');

        if (empty($phone)) {
            throw new RuntimeException(
                'SUPER_ADMIN_PHONE در تنظیمات محیطی (env) تنظیم نشده است.'
            );
        }

        $admin = User::where('phone', $phone)->first();

        if (!$admin) {
            User::create([
                'phone' => $phone,
                'password' => Hash::make($password),
                'first_name' => 'مدیر',
                'last_name' => 'سیستم',
                'email' => 'admin@arano.local',
                'role' => 'SUPER_ADMIN',
                'status' => 'APPROVED',
                'is_active' => true,
            ]);

            return;
        }

        /*
         * اگر Super Admin از قبل وجود دارد،
         * اطلاعات فعلی او را حفظ می‌کنیم و Password را
         * با هر بار اجرای Seeder تغییر نمی‌دهیم.
         */
        $admin->update([
            'role' => 'SUPER_ADMIN',
            'status' => 'APPROVED',
            'is_active' => true,
        ]);
    }
}