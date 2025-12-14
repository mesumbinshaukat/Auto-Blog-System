<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Blog;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContentFormattingTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_content_structure_in_view()
    {
        $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);
        
        $content = "<h1>Title</h1><p>Para 1</p><h2>Heading 2</h2><p>Para 2</p>";
        
        $blog = Blog::create([
            'title' => 'Test Blog',
            'slug' => 'test-blog',
            'content' => $content,
            'category_id' => $category->id,
            'published_at' => now(),
            'meta_title' => 'Meta Title',
            'meta_description' => 'Meta Desc',
            'tags_json' => ['test'],
        ]);

        $response = $this->get(route('blog.show', $blog->slug));
        
        $response->assertStatus(200);
        $response->assertSee('prose prose-lg', false); 
        $response->assertSee('style="line-height: 1.8;"', false);
    }
    
    public function test_thumbnail_display_in_view()
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('thumbnails/test.svg', 'dummy content');

        $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);
        $blog = Blog::create([
            'title' => 'Test Blog With Thumb',
            'slug' => 'test-blog-thumb',
            'content' => '<p>Content</p>',
            'category_id' => $category->id,
            'thumbnail_path' => 'thumbnails/test.svg',
            'published_at' => now(),
            'views' => 0
        ]);

        $response = $this->get(route('blog.show', $blog->slug));
        
        // Check if either the test SVG or the fallback (if storage fake fails) is present
        // In testing, asset() might return different base URLs
        $url = asset('storage/thumbnails/test.svg');
        $response->assertSee($url, false);
    }
}
