<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Services\BlogGeneratorService;
use App\Models\Category;
use Illuminate\Support\Facades\Log;

class RealContentSeeder extends Seeder
{
    protected $generator;

    public function __construct(BlogGeneratorService $generator)
    {
        $this->generator = $generator;
    }

    public function run(): void
    {
        $categories = Category::whereIn('slug', ['technology', 'ai'])->get();
        
        if ($categories->isEmpty()) {
            $this->command->error("Categories not found. Run DatabaseSeeder first.");
            return;
        }

        $this->command->info("Starting real content generation for " . $categories->count() . " categories...");

        foreach ($categories as $category) {
            try {
                $this->command->info("Generating blog for category: {$category->name}...");
                $blog = $this->generator->generateBlogForCategory($category);
                
                if ($blog) {
                    $this->command->info("✅ Blog Created: " . $blog->title);
                } else {
                    $this->command->warn("⚠️ Skipped (duplicate or failure) for: {$category->name}");
                }
            } catch (\Exception $e) {
                $this->command->error("❌ Error generating for {$category->name}: " . $e->getMessage());
                Log::error($e);
            }
        }
    }
}
