<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Category;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $latest = Blog::with('category')->latest('published_at')->take(5)->get();
        // Eager load 6 latest blogs for each category for the carousels
        $categories = Category::with(['blogs' => function($query) {
            $query->latest('published_at')->take(6);
        }])->get();
        
        // Group recent blogs by category for the grid
        $feed = Blog::with('category')
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->paginate(10);

        return view('welcome', compact('latest', 'categories', 'feed'));
    }

    public function category($slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();
        $feed = $category->blogs()->latest('published_at')->paginate(10);
        $categories = Category::all();

        return view('welcome', compact('feed', 'categories', 'category'));
    }

    public function show($slug)
    {
        $blog = Blog::where('slug', $slug)->with('category')->firstOrFail();
        
        // Increment views
        // Analytics Tracking
        $ip = request()->ip();
        $userAgent = request()->userAgent();
        $cacheKey = 'viewed_blog_' . $blog->id . '_' . $ip;

        if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            // Record view in database
            \App\Models\BlogView::create([
                'blog_id' => $blog->id,
                'ip_address' => $ip,
                'user_agent' => substr($userAgent, 0, 255), // Truncate if too long for text column? (It's text, so fine)
                'referer' => substr(request()->header('referer'), 0, 255),
                'country_code' => request()->header('CF-IPCountry', 'XX'), // Cloudflare support
                'read_time_seconds' => 0 // Initial load
            ]);

            // Increment detailed counter (and cache count)
            $blog->increment('views');

            // Set cooldown for 1 hour
            \Illuminate\Support\Facades\Cache::put($cacheKey, true, 3600);
        }

        $related = Blog::where('category_id', $blog->category_id)
            ->where('id', '!=', $blog->id)
            ->take(3)
            ->get();

        return view('blogs.show', [
            'blog' => $blog, 
            'related' => $related,
            'meta_title' => $blog->meta_title,
            'meta_description' => $blog->meta_description,
            'og_image' => "https://placehold.co/1200x630/e2e8f0/1e293b?text={$blog->category->name}"
        ]);
    }
}
