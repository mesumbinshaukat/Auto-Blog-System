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
            if (str_contains($newContent, '\u00') || str_contains($newContent, '**') || str_contains($newContent, '---')) {
                $decoded = $this->fullCleanup($newContent);
                if ($decoded && $decoded !== $newContent) {
                    $newContent = $decoded;
                    $needsUpdate = true;
                }
            }

            // Check and decode Meta Description
            if (str_contains($newMetaDesc, '\u00') || str_contains($newMetaDesc, '**')) {
                $decoded = $this->fullCleanup($newMetaDesc);
                if ($decoded && $decoded !== $newMetaDesc) {
                    $newMetaDesc = $decoded;
                    $needsUpdate = true;
                }
            }

            // Check and decode Title
            if (str_contains($newTitle, '\u00') || str_contains($newTitle, '**')) {
                $decoded = $this->fullCleanup($newTitle);
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
     * Decode Unicode and clean Markdown artifacts
     */
    private function fullCleanup(string $content): ?string
    {
        try {
            // 1. Resolve Unicode Escapes (e.g. \\u003ch1\\u003e -> <h1>)
            if (str_contains($content, '\u00')) {
                $content = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
                    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
                }, $content);
            }

            // 2. Markdown Bold/Italic Cleanup
            $content = preg_replace('/\*\*(.*?)\*\*/u', '<strong>$1</strong>', $content);
            $content = preg_replace('/(?<!\*)\*(?!\*)(.*?)(?<!\*)\*(?!\*)/u', '<em>$1</em>', $content);

            // 3. Header and Divider Cleanup
            // Fix malformed header artifacts like <h3 id=""></h3>Topic</strong> -> <h3>Topic</h3>
            $content = preg_replace('/<h3[^>]*>\s*<\/h3>\s*(?:<strong>|<b>)?(.*?)(?:<\/strong>|<\/b>)?/is', '<h3>$1</h3>', $content);
            
            // Clean up "--- ###" or similar header leaks
            $content = preg_replace('/---\s*###\s*(<strong>|<b>)?(.*?)(<\/strong>|<\/b>)?/i', '<h3>$2</h3>', $content);
            
            // Remove trailing Markdown separators at ends of paragraphs/lines
            $content = preg_replace('/\s*---(?=\s*(?:<\/p>|<br|$))/i', '', $content);
            
            // Replace standalone "---" with <hr>
            $content = preg_replace('/^\s*---\s*$/m', '<hr>', $content);

            return $content;
        } catch (\Exception $e) {
            Log::error("Cleanup failed for blog: " . $e->getMessage());
            return $content;
        }
    }
}
