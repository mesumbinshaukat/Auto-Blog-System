<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Category;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index()
    {
        $sitemaps = [];

        // 1. Static Pages
        $sitemaps[] = [
            'loc' => route('sitemap.pages'),
            'lastmod' => now()->startOfMonth() // Static pages change rarely
        ];

        // 2. Categories
        $lastCategory = Category::latest('updated_at')->first();
        $sitemaps[] = [
            'loc' => route('sitemap.categories'),
            'lastmod' => $lastCategory ? $lastCategory->updated_at : now()
        ];

        // 3. Blogs (Pagination)
        $blogCount = Blog::count();
        $pages = ceil($blogCount / 1000);
        
        for ($i = 1; $i <= $pages; $i++) {
            $sitemaps[] = [
                'loc' => route('sitemap.blogs', ['page' => $i]),
                'lastmod' => now() // Blogs update frequently
            ];
        }

        return response()->view('sitemap.index', compact('sitemaps'))
            ->header('Content-Type', 'text/xml');
    }

    public function pages()
    {
        $urls = [
            route('home'),
            route('privacy-policy'),
            route('terms-conditions')
        ];

        return response()->view('sitemap.pages', compact('urls'))
            ->header('Content-Type', 'text/xml');
    }

    public function blogs($page = 1)
    {
        $limit = 1000;
        $offset = ($page - 1) * $limit;
        
        $blogs = Blog::latest()
            ->skip($offset)
            ->take($limit)
            ->get(['slug', 'updated_at']);

        return response()->view('sitemap.blogs', compact('blogs'))
            ->header('Content-Type', 'text/xml');
    }

    public function categories()
    {
        $categories = Category::all(['slug', 'updated_at']);

        return response()->view('sitemap.categories', compact('categories'))
            ->header('Content-Type', 'text/xml');
    }
}
