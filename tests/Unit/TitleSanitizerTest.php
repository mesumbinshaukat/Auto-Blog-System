<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TitleSanitizerService;
use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TitleSanitizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sanitizes_malformed_html_entities()
    {
        $service = new TitleSanitizerService();

        $this->assertEquals("Spain’s History", $service->sanitizeTitle("Spain&rsquo;s History"));
        $this->assertEquals("Rock & Roll", $service->sanitizeTitle("Rock &amp; Roll")); // Should become & if desired, or stay same if double encoded?
        // Note: html_entity_decode('Rock &amp; Roll') -> 'Rock & Roll'. 
        // If the title IS "Rock & Roll" (literal), preg_match for entity will fail (no ;) or succeed if &something; exists.
        
        // Literal &
        $this->assertEquals("Rock & Roll", $service->sanitizeTitle("Rock & Roll")); 

        // Quotes
        $this->assertEquals('"Quote"', $service->sanitizeTitle("&quot;Quote&quot;"));
        $this->assertEquals("'Quote'", $service->sanitizeTitle("&#039;Quote&#039;"));
    }

    public function test_it_fixes_blog_model()
    {
        $service = new TitleSanitizerService();
        $blog = Blog::factory()->create([
            'title' => "World&rsquo;s Best",
            'slug' => 'worlds-best',
            'meta_title' => "World&rsquo;s Best"
        ]);

        $service->fixBlog($blog);

        $this->assertDatabaseHas('blogs', [
            'id' => $blog->id,
            'title' => "World’s Best",
            'meta_title' => "World’s Best"
        ]);
        
        $blog->refresh();
        $this->assertNotEquals('worlds-best', $blog->slug); // Slug should change
    }
}
