<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Blog;
use Illuminate\Support\Str;

class FixSeoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blog:fix-seo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix SEO meta data and inject internal links for existing blogs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $blogs = Blog::all();
        $bar = $this->output->createProgressBar($blogs->count());
        $bar->start();

        foreach ($blogs as $blog) {
            $updated = false;

            // 1. Fix Meta Title
            if (empty($blog->meta_title) || !str_contains($blog->meta_title, ' - ')) {
                 $blog->meta_title = Str::limit($blog->title, 55) . ' - ' . config('app.name', 'AutoBlog');
                 $updated = true;
            }

            // 2. Fix Meta Desc
            if (empty($blog->meta_description)) {
                $blog->meta_description = Str::limit(strip_tags($blog->content), 155);
                $updated = true;
            }

            // 3. Inject Internal Links if none exist and content is long enough
            // Note: This relies on simple string check. Better to check actual HTML.
            // Using a loose check for internal route.
            if (!str_contains($blog->content, 'route(\'blog.show\'') && !str_contains($blog->content, 'blog/')) {
                 // Reuse service logic? 
                 // Cannot easily reuse protected method without reflecting or exposing it.
                 // For now, let's skip complex content modification in this simple fix command
                 // to avoid breaking things without full service context. 
                 // Or we can duplicate the simple logic.
                 
                 // Let's implement the linking logic here simplified.
                 $related = Blog::where('category_id', $blog->category_id)
                    ->where('id', '!=', $blog->id)
                    ->latest()
                    ->take(3)
                    ->get();
                    
                 if ($related->count() > 0) {
                     $blog->content = $this->injectLinks($blog->content, $related);
                     $updated = true;
                 }
            }

            if ($updated) {
                $blog->saveQuietly(); // Don't trigger observer to regen sitemap 100 times
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('SEO fixed and internal links injected where missing.');
        
        // Regenerate sitemap once at end
        $this->info('Regenerating sitemap...');
        // Manually trigger observer logic or use spatie package
        // Since we are in command, we can just use the observer logic instance
        // or just rely on the next update/cron.
        // Let's force a save on the last one to trigger it? No, explicit call is better.
        
        if ($blogs->count() > 0) {
             (new \App\Observers\BlogObserver)->created($blogs->first()); // Trigger regen
        }
    }
    
    protected function injectLinks(string $html, $relatedBlogs): string
    {
         // Simplified logic from Service
        if ($relatedBlogs->isEmpty()) return $html;

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $paragraphs = $dom->getElementsByTagName('p');
        $pCount = $paragraphs->length;
        if ($pCount < 3) return $html; 

        $positions = [1, (int)($pCount / 2), $pCount - 2];
        $blogIndex = 0;
        
        foreach ($positions as $index) {
             if ($blogIndex >= $relatedBlogs->count()) break;
             $targetP = $paragraphs->item($index);
             
             if ($targetP) {
                 $rBlog = $relatedBlogs[$blogIndex];
                 // Hardcoded route logic since we might not have route helper available same way or just use slug
                 $url = url('blog/' . $rBlog->slug); // Assuming URL structure
                 $title = htmlspecialchars($rBlog->title);
                 
                 $newP = $dom->createElement('p');
                 $frag = $dom->createDocumentFragment();
                 $frag->appendXML("<em>Related: <a href='$url' rel='dofollow'>$title</a></em>");
                 $newP->appendChild($frag);
                 $targetP->parentNode->insertBefore($newP, $targetP->nextSibling);
                 $blogIndex++;
             }
        }
        
        return str_replace('<?xml encoding="utf-8" ?>', '', $dom->saveHTML());
    }
}
