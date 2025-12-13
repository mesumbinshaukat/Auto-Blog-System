<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Category;
use App\Services\BlogGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function index()
    {
        $blogs = Blog::latest()->paginate(20);
        return view('dashboard', compact('blogs'));
    }

    public function create()
    {
        $categories = Category::all();
        return view('blogs.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'content' => 'required',
            'category_id' => 'required|exists:categories,id',
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $validated['published_at'] = now();
        
        Blog::create($validated);

        return redirect()->route('admin.dashboard')->with('success', 'Blog created successfully.');
    }

    public function edit(Blog $blog)
    {
        $categories = Category::all();
        return view('blogs.edit', compact('blog', 'categories'));
    }

    public function update(Request $request, Blog $blog)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'content' => 'required',
            'category_id' => 'required|exists:categories,id',
        ]);

        $blog->update($validated);

        return redirect()->route('admin.dashboard')->with('success', 'Blog updated.');
    }

    public function destroy(Blog $blog)
    {
        $blog->delete();
        return back()->with('success', 'Blog deleted.');
    }

    public function generate(Request $request, BlogGeneratorService $generator)
    {
        $request->validate(['category_id' => 'required|exists:categories,id']);
        
        $category = Category::find($request->category_id);
        $blog = $generator->generateBlogForCategory($category);

        return back()->with('success', "Blog generated: {$blog->title}");
    }
}
