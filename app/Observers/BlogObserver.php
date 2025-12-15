<?php

namespace App\Observers;

use App\Models\Blog;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class BlogObserver
{
    /**
     * Handle the Blog "created" event.
     */
    public function created(Blog $blog): void
    {
        $this->updateSitemap($blog);
    }

    /**
     * Handle the Blog "updated" event.
     */
    public function updated(Blog $blog): void
    {
        // Only update if slug changed or major update, but simplest is just ensuring it's in sitemap
        // For performance on large sites, we might want to just append.
        // But spatie/laravel-sitemap manual generation is often usually full regen or append.
        // Let's rely on a console command for full regen, but here we can try to add it.
        // Actually, regenerating the WHOLE sitemap on every save is heavy.
        // Better approach: Just log it or queue a job.
        // But requested: "generate/update sitemap.xml on blog create/update"
        
        // We will try to just add this one URL if possible, or regenerate if file small.
        // Spatie Sitemap::create() overwrites.
        // Let's assume we want to regenerate the main sitemap for now as it's the requested feature.
        // To avoid performance hit, we'll verify if we can append. 
        // Spatie doesn't support easy "append" to existing file without loading it.
        
        // Optimally: Dispatch a job given it might take time.
        // For now, direct execution as per request flow "generate/update sitemap... on blog create".
        
         $this->updateSitemap($blog);
    }

    /**
     * Handle the Blog "deleted" event.
     */
    public function deleted(Blog $blog): void
    {
        // Full regeneration needed to remove.
        $this->regenerateSitemap();
    }

    protected function updateSitemap(Blog $blog): void
    {
        // For simplicity and correctness in this phase, we will regenerate. 
        // In high scale, this should be a scheduled job (e.g. hourly).
        // Check if we are in a seeder or mass insert to avoid IO thrashing?
        // We'll trust the single blog flow.
        
        $this->regenerateSitemap();
    }
    
    protected function regenerateSitemap()
    {
        // Create sitemap
        $sitemap = Sitemap::create();
        
        // Add static pages
        $sitemap->add(Url::create('/')->setPriority(1.0)->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY));
        
        // Add Blogs (chunking if needed, but for now all)
        Blog::all()->each(function (Blog $blogItem) use ($sitemap) {
            $sitemap->add(Url::create(route('blog.show', $blogItem->slug))
                ->setLastModificationDate($blogItem->updated_at)
                ->setPriority(0.8)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY));
        });
        
        // Categories
        \App\Models\Category::all()->each(function ($cat) use ($sitemap) {
             $sitemap->add(Url::create(route('category', $cat->slug)) // Using 'category' route name from web.php
                ->setPriority(0.9)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY));
        });

        $sitemap->writeToFile(public_path('sitemap.xml'));
    }
}
