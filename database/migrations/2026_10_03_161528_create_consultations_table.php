<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();

            $table->string('phone', 20);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->text('message');

            $table->string('status')
                ->default('UNANSWERED');

            $table->timestamps();

            $table->index('status');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};