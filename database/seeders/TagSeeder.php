<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            [
                'name' => 'WordPress',
                'slug' => 'wordpress',
            ],
            [
                'name' => 'PHP',
                'slug' => 'php',
            ],
            [
                'name' => 'Laravel',
                'slug' => 'laravel',
            ],
            [
                'name' => 'فروشگاهی',
                'slug' => 'ecommerce',
            ],
            [
                'name' => 'شرکتی',
                'slug' => 'corporate',
            ],
        ];

        foreach ($tags as $tag) {
            Tag::updateOrCreate(
                ['slug' => $tag['slug']],
                [
                    'name' => $tag['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}