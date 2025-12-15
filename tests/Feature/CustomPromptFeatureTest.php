<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Blog;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class CustomPromptFeatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test custom prompt field is present in create form
     */
    public function test_custom_prompt_field_in_create_form(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());
        
        $response = $this->get(route('admin.blogs.create'));
        
        $response->assertStatus(200);
        $response->assertSee('Custom Prompt');
        $response->assertSee('name="custom_prompt"', false);
        $response->assertSee('maxlength="2000"', false);
    }

    /**
     * Test blog can be created with custom prompt
     */
    public function test_blog_created_with_custom_prompt(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());
        $category = Category::factory()->create();
        
        $response = $this->post(route('admin.blogs.store'), [
            'title' => 'Test Blog',
            'content' => '<p>Test content</p>',
            'category_id' => $category->id,
            'custom_prompt' => 'Compare React vs Vue for beginners'
        ]);
        
        $response->assertRedirect(route('admin.dashboard'));
        
        $blog = Blog::where('title', 'Test Blog')->first();
        $this->assertNotNull($blog);
        $this->assertEquals('Compare React vs Vue for beginners', $blog->custom_prompt);
    }

    /**
     * Test custom prompt is optional
     */
    public function test_custom_prompt_is_optional(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());
        $category = Category::factory()->create();
        
        $response = $this->post(route('admin.blogs.store'), [
            'title' => 'Test Blog Without Prompt',
            'content' => '<p>Test content</p>',
            'category_id' => $category->id
        ]);
        
        $response->assertRedirect(route('admin.dashboard'));
        
        $blog = Blog::where('title', 'Test Blog Without Prompt')->first();
        $this->assertNotNull($blog);
        $this->assertNull($blog->custom_prompt);
    }

    /**
     * Test custom prompt is truncated to 2000 characters
     */
    public function test_custom_prompt_truncated_to_2000_chars(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());
        $category = Category::factory()->create();
        
        $longPrompt = str_repeat('A', 2500);
        
        $response = $this->post(route('admin.blogs.store'), [
            'title' => 'Test Blog Long Prompt',
            'content' => '<p>Test content</p>',
            'category_id' => $category->id,
            'custom_prompt' => $longPrompt
        ]);
        
        $blog = Blog::where('title', 'Test Blog Long Prompt')->first();
        $this->assertNotNull($blog);
        $this->assertEquals(2000, strlen($blog->custom_prompt));
    }

    /**
     * Test custom prompt validation rejects over 2000 chars
     */
    public function test_custom_prompt_validation_max_2000(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());
        $category = Category::factory()->create();
        
        $longPrompt = str_repeat('A', 2001);
        
        $response = $this->post(route('admin.blogs.store'), [
            'title' => 'Test Blog',
            'content' => '<p>Test content</p>',
            'category_id' => $category->id,
            'custom_prompt' => $longPrompt
        ]);
        
        // Should still succeed but truncate
        $response->assertRedirect();
        $blog = Blog::where('title', 'Test Blog')->first();
        $this->assertEquals(2000, strlen($blog->custom_prompt));
    }

    /**
     * Test custom prompt is stored in database
     */
    public function test_custom_prompt_stored_in_database(): void
    {
        $category = Category::factory()->create();
        
        $blog = Blog::create([
            'title' => 'Test Blog',
            'slug' => 'test-blog',
            'content' => '<p>Test content</p>',
            'category_id' => $category->id,
            'custom_prompt' => 'Focus on practical examples',
            'published_at' => now()
        ]);
        
        $this->assertDatabaseHas('blogs', [
            'id' => $blog->id,
            'custom_prompt' => 'Focus on practical examples'
        ]);
    }

    /**
     * Test custom prompt field is fillable
     */
    public function test_custom_prompt_is_fillable(): void
    {
        $fillable = (new Blog())->getFillable();
        
        $this->assertContains('custom_prompt', $fillable);
    }

    /**
     * Test custom prompt with special characters
     */
    public function test_custom_prompt_with_special_characters(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());
        $category = Category::factory()->create();
        
        $promptWithSpecialChars = 'Compare "React" vs \'Vue\' & Angular <framework>';
        
        $response = $this->post(route('admin.blogs.store'), [
            'title' => 'Test Special Chars',
            'content' => '<p>Test content</p>',
            'category_id' => $category->id,
            'custom_prompt' => $promptWithSpecialChars
        ]);
        
        $blog = Blog::where('title', 'Test Special Chars')->first();
        $this->assertEquals($promptWithSpecialChars, $blog->custom_prompt);
    }

    /**
     * Test custom prompt with URLs
     */
    public function test_custom_prompt_with_urls(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());
        $category = Category::factory()->create();
        
        $promptWithUrl = 'Include data from https://example.com/stats and https://api.example.com/data';
        
        $response = $this->post(route('admin.blogs.store'), [
            'title' => 'Test URL Prompt',
            'content' => '<p>Test content</p>',
            'category_id' => $category->id,
            'custom_prompt' => $promptWithUrl
        ]);
        
        $blog = Blog::where('title', 'Test URL Prompt')->first();
        $this->assertEquals($promptWithUrl, $blog->custom_prompt);
        
        // Verify URLs are preserved
        $this->assertStringContainsString('https://example.com/stats', $blog->custom_prompt);
        $this->assertStringContainsString('https://api.example.com/data', $blog->custom_prompt);
    }
}
