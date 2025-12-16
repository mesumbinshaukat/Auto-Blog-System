<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Category;
use App\Services\BlogGeneratorService;

class GenerateCustomBlog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blog:generate-custom {prompt : The text prompt or URL to generate from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a blog post from a custom prompt or URL (simulating admin dashboard)';

    protected $generator;

    public function __construct(BlogGeneratorService $generator)
    {
        parent::__construct();
        $this->generator = $generator;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $prompt = $this->argument('prompt');
        $this->info("Generating blog from prompt: '$prompt'");

        // Use first active category or create one if none
        $category = Category::first();
        if (!$category) {
            $this->warn("No categories found. Creating 'General'...");
            $category = Category::create(['name' => 'General', 'slug' => 'general']);
        }

        $this->info("Using Category: {$category->name}");

        $blog = $this->generator->generateBlogForCategory($category, function($status, $progress) {
            $this->line("[$progress%] $status");
        }, $prompt);

        if ($blog) {
            $this->info("\nSUCCESS! Blog Generated:");
            $this->info("Title: " . $blog->title);
            $this->info("URL: " . url('/blog/' . $blog->slug));
            $this->info("Custom Prompt: " . $blog->custom_prompt);
            $this->newLine();
            $this->info("Excerpt:");
            $this->line(substr(strip_tags($blog->content), 0, 300) . "...");
        } else {
            $this->error("\nFAILED: Blog generation returned null.");
        }
    }
}
