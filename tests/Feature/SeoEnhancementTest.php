<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Blog;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

class SeoEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_exists_and_is_correct()
    {
        $this->assertTrue(File::exists(public_path('robots.txt')));
        $content = File::get(public_path('robots.txt'));
        $this->assertStringContainsString('Sitemap:', $content);
        $this->assertStringContainsString('Allow: /', $content);
    }

    public function test_blog_observer_creates_sitemap()
    {
        // Mocking spattie sitemap might be hard, so checking file creation
        // Ensure directory exists
        
        // Clean up
        if(File::exists(public_path('sitemap.xml'))) {
            File::delete(public_path('sitemap.xml'));
        }

        $blog = Blog::factory()->create();

        // Observer should have fired
        $this->assertTrue(File::exists(public_path('sitemap.xml')));
        
        $content = File::get(public_path('sitemap.xml'));
        $this->assertStringContainsString($blog->slug, $content);
    }
    
    public function test_fix_seo_command()
    {
        $category = Category::factory()->create();
        // Create blog with poor meta and no links
        $blog = Blog::factory()->create([
            'category_id' => $category->id,
            'meta_title' => 'Bad Title',
            'meta_description' => '',
            'content' => '<p>Para 1</p><p>Para 2</p><p>Para 3</p>'
        ]);
        
        // Create related blog
        $related = Blog::factory()->create(['category_id' => $category->id]);

        $this->artisan('blog:fix-seo')
             ->assertExitCode(0);
             
        $blog->refresh();
        
        // Check Meta
        $this->assertStringContainsString(' - AutoBlog', $blog->meta_title);
        $this->assertNotEmpty($blog->meta_description);
        
        // Check Links
        $this->assertStringContainsString($related->slug, $blog->content);
        $this->assertStringContainsString('dofollow', $blog->content);
    }
}
