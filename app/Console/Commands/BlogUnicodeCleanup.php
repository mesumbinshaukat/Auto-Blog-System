<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Blog;
use Illuminate\Support\Facades\Log;

class BlogUnicodeCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blog:cleanup-unicode {id? : Optional ID of the blog to clean}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up Unicode escaped characters (like \u003c) from blog content and meta fields';

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

        $this->info("Found " . $blogs->count() . " blog(s) to check for Unicode issues.");

        $bar = $this->output->createProgressBar($blogs->count());
        $fixedCount = 0;

        foreach ($blogs as $blog) {
            $needsUpdate = false;
            $newContent = $blog->content;
            $newMetaDesc = $blog->meta_description;
            $newTitle = $blog->title;

            // Check and decode Content
            if (str_contains($newContent, '\u00')) {
                $decoded = $this->decodeUnicode($newContent);
                if ($decoded && $decoded !== $newContent) {
                    $newContent = $decoded;
                    $needsUpdate = true;
                }
            }

            // Check and decode Meta Description
            if (str_contains($newMetaDesc, '\u00')) {
                $decoded = $this->decodeUnicode($newMetaDesc);
                if ($decoded && $decoded !== $newMetaDesc) {
                    $newMetaDesc = $decoded;
                    $needsUpdate = true;
                }
            }

            // Check and decode Title
            if (str_contains($newTitle, '\u00')) {
                $decoded = $this->decodeUnicode($newTitle);
                if ($decoded && $decoded !== $newTitle) {
                    $newTitle = $decoded;
                    $needsUpdate = true;
                }
            }

            if ($needsUpdate) {
                $blog->update([
                    'content' => $newContent,
                    'meta_description' => $newMetaDesc,
                    'title' => $newTitle
                ]);
                $fixedCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Completed. Fixed $fixedCount blogs.");
    }

    /**
     * Decode Unicode escaped strings safely
     */
    private function decodeUnicode(string $content): ?string
    {
        try {
            // Handle cases where content is wrapped in quotes or not
            $testStr = $content;
            if (!str_starts_with($testStr, '"')) {
                $testStr = '"' . str_replace('"', '\"', $testStr) . '"';
            }
            
            $decoded = json_decode($testStr);
            
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                // Fallback for very complex strings
                return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
                    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
                }, $content);
            }
            
            return $decoded;
        } catch (\Exception $e) {
            Log::error("Unicode decoding failed for blog: " . $e->getMessage());
            return $content;
        }
    }
}
