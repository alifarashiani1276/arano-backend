<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            // صاحب پروژه
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // دسته‌بندی پروژه
            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->string('title');

            $table->text('description');

            // بودجه پروژه
            $table->decimal('budget', 15, 2);

            // مهلت انجام پروژه
            $table->date('deadline');

            // وضعیت پروژه
            $table->string('status')->default('PENDING');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};