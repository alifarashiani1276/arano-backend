<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->string('purpose', 50)->default('user_auth');

            $table->index(
                ['phone', 'purpose', 'used_at'],
                'otp_codes_phone_purpose_used_at_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropIndex('otp_codes_phone_purpose_used_at_idx');
            $table->dropColumn('purpose');
        });
    }
};