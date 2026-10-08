<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::updateOrCreate(
            ['slug' => 'personal-website'],
            [
                'name' => 'طراحی سایت شخصی',
                'description' => 'طراحی و توسعه وب‌سایت‌های شخصی',
                'is_active' => true,
            ]
        );

        Category::updateOrCreate(
            ['slug' => 'corporate-website'],
            [
                'name' => 'طراحی سایت سازمانی',
                'description' => 'طراحی و توسعه وب‌سایت‌های سازمانی و شرکتی',
                'is_active' => true,
            ]
        );
    }
}