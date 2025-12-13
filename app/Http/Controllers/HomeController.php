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
        $categories = Category::all();
        
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
        $blog->increment('views');

        $related = Blog::where('category_id', $blog->category_id)
            ->where('id', '!=', $blog->id)
            ->take(3)
            ->get();

        return view('blogs.show', [
            'blog' => $blog, 
            'related' => $related,
            'meta_title' => $blog->meta_title,
            'meta_description' => $blog->meta_description,
            'og_image' => "https://source.unsplash.com/random/1200x630?{$blog->category->slug}&sig={$blog->id}"
        ]);
    }
}
