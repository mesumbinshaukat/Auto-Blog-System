<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Technology',
            'Business',
            'AI',
            'Games',
            'Politics',
            'Sports',
            'Tutorial',
            'Science',
            'Health'
        ];

        foreach ($categories as $name) {
            Category::updateOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name)]
            );
        }
    }
}
