<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Blog;
use App\Services\AIService;
use Illuminate\Support\Facades\Log;

class ReformatBlogContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blog:reformat {id? : Optional ID of the blog to reformat}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reformat blog content using AI to ensure HTML compliance';

    protected $aiService;

    public function __construct(AIService $aiService)
    {
        parent::__construct();
        $this->aiService = $aiService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');

        if ($id) {
            $blogs = Blog::where('id', $id)->get();
        } else {
            $blogs = Blog::all();
        }

        $this->info("Found " . $blogs->count() . " blog(s) to reformat.");

        $bar = $this->output->createProgressBar($blogs->count());

        foreach ($blogs as $blog) {
            try {
                // Skip if content seems short or empty
                if (strlen($blog->content) < 100) {
                    $this->warn("Skipping blog #{$blog->id} (Content too short)");
                    continue;
                }

                $this->info("\nOptimizing & Humanizing: " . $blog->title);

                // optimizeAndHumanize now handles regex cleaning for em dashes
                $optimizedData = $this->aiService->optimizeAndHumanize($blog->content);
                $newContent = $optimizedData['content'];
                $toc = $optimizedData['toc'];

                $blog->update([
                    'content' => $newContent,
                    'table_of_contents_json' => $toc
                ]);
                
                $this->info("Done.");

            } catch (\Exception $e) {
                $this->error("Failed to reformat blog #{$blog->id}: " . $e->getMessage());
                Log::error("Formatting failed for blog #{$blog->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Reformatting process completed.");
    }
}
