<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->input('q');
        
        $blogs = \App\Models\Blog::query()
            ->when($query, function ($q) use ($query) {
                return $q->where('title', 'like', "%{$query}%")
                         ->orWhere('content', 'like', "%{$query}%");
            })
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('search.index', compact('blogs', 'query'));
    }
}
