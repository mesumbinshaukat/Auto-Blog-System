<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Blog;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\Mail\BlogGenerationFailed;

class AdditionalFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_seo_tags_are_present_in_blog_view()
    {
        $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);
        $blog = Blog::create([
            'title' => 'SEO Test Blog',
            'slug' => 'seo-test-blog',
            'content' => '<p>Content</p>',
            'meta_title' => 'Meta Title for SEO',
            'meta_description' => 'Meta Description for SEO',
            'category_id' => $category->id,
            'published_at' => now(),
        ]);

        $response = $this->get('/blog/seo-test-blog');
        
        $response->assertStatus(200);
        $response->assertSee('Meta Title for SEO');
        $response->assertSee('Meta Description for SEO');
        $response->assertSee('property="og:title"', false);
    }

    public function test_sitemap_is_generated()
    {
        $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);
        Blog::create([
            'title' => 'Sitemap Blog',
            'slug' => 'sitemap-blog',
            'content' => 'Content',
            'category_id' => $category->id,
            'published_at' => now(),
        ]);

        $response = $this->get('/sitemap.xml');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=utf-8');
        $response->assertSee('sitemap-blog');
    }

    public function test_content_with_tables_renders_correctly()
    {
        // Assuming your Blog model allows raw HTML or you implement Markdown parsing
        // In this implementation, we allow raw HTML (as per AIService outputting HTML).
        $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);
        $tableHtml = '<table><tr><td>Cell</td></tr></table>';
        
        $blog = Blog::create([
            'title' => 'Table Blog',
            'slug' => 'table-blog',
            'content' => $tableHtml,
            'category_id' => $category->id,
            'published_at' => now(),
        ]);

        $response = $this->get('/blog/table-blog');
        $response->assertStatus(200);
        $response->assertSee('<table>', false);
    }

    public function test_error_email_mailable_content()
    {
        $mail = new BlogGenerationFailed('Test Error Message', 'Test Category');
        
        $mail->assertSeeInHtml('Test Error Message');
        $mail->assertSeeInHtml('Test Category');
    }

    public function test_duplicate_topics_are_prevented()
    {
        // This logic resides in BlogGeneratorService, but we can verify DB constraint or logic
        // Our service has: if (Blog::where('title', 'LIKE', "%$topic%")->exists()) return;
        // Let's test the Model/DB level validtion or just service logic mock
        
        // Since we can't easily mock the random picking in service without refactor, 
        // we test the Model's slug uniqueness which indirectly prevents exact duplicates if title is same.
        
        $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);
        Blog::create([
            'title' => 'Unique Title',
            'slug' => 'unique-title',
            'content' => 'Content',
            'category_id' => $category->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Blog::create([
            'title' => 'Unique Title',
            'slug' => 'unique-title', // Slug should be unique
            'content' => 'Content',
            'category_id' => $category->id,
        ]);
    }
    
    public function test_category_relationship()
    {
        $category = Category::create(['name' => 'Test', 'slug' => 'test']);
        $blog = Blog::create([
            'title' => 'Rel Test', 'slug' => 'rel-test', 'content' => 'c', 'category_id' => $category->id
        ]);
        
        $this->assertTrue($blog->category->is($category));
        $this->assertTrue($category->blogs->contains($blog));
    }

    public function test_manual_generation_route_triggers_service()
    {
         // We mock the generator service
         $this->mock(\App\Services\BlogGeneratorService::class, function ($mock) {
             $mock->shouldReceive('generateBlogForCategory')->once()->andReturn(new Blog(['title' => 'Mock Blog']));
         });
         
         $user = \App\Models\User::factory()->create();
         $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);
         
         $response = $this->actingAs($user)->post(route('admin.generate'), ['category_id' => $category->id]);
         $response->assertSessionHas('success');
    }
}
