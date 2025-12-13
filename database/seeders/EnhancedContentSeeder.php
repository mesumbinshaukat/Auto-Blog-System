<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Services\BlogGeneratorService;
use App\Models\Category;
use Illuminate\Support\Facades\Log;

class EnhancedContentSeeder extends Seeder
{
    protected $generator;

    public function __construct(BlogGeneratorService $generator)
    {
        $this->generator = $generator;
    }

    public function run(): void
    {
        // Generate 3 high-quality blogs from different categories
        $categories = Category::whereIn('slug', ['technology', 'ai', 'business'])->get();
        
        if ($categories->count() < 3) {
            $this->command->error("Not enough categories. Run DatabaseSeeder first.");
            return;
        }

        $this->command->info("🚀 Generating 3 high-quality real-world blogs...");
        $this->command->info("This will take 30-60 seconds as we're using real AI APIs.\n");

        foreach ($categories as $index => $category) {
            try {
                $this->command->info("[" . ($index + 1) . "/3] Generating blog for category: {$category->name}...");
                $this->command->info("  → Scraping trending topics...");
                $this->command->info("  → Researching from Wikipedia...");
                $this->command->info("  → Generating content via Hugging Face...");
                $this->command->info("  → Optimizing with Google Gemini...");
                
                $blog = $this->generator->generateBlogForCategory($category);
                
                if ($blog) {
                    $wordCount = str_word_count(strip_tags($blog->content));
                    $this->command->info("  ✅ SUCCESS: \"{$blog->title}\"");
                    $this->command->info("     Words: {$wordCount} | Slug: {$blog->slug}\n");
                } else {
                    $this->command->warn("  ⚠️  Skipped (duplicate topic) for: {$category->name}\n");
                }
                
                // Small delay between generations to respect API rate limits
                if ($index < 2) {
                    sleep(2);
                }
                
            } catch (\Exception $e) {
                $this->command->error("  ❌ ERROR for {$category->name}: " . $e->getMessage() . "\n");
                Log::error($e);
            }
        }

        $this->command->info("✨ Blog generation complete!");
        $this->command->info("Visit http://127.0.0.1:8000 to see your blogs.");
    }
}
