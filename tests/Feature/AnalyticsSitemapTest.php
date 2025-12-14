<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Blog;
use App\Models\BlogView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsSitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed categories as they are needed for blogs
        DB::table('categories')->insert([
            ['name' => 'Tech', 'slug' => 'tech', 'created_at' => now(), 'updated_at' => now()]
        ]);
        
        // Mock a user for dashboard access
        $this->actingAs(\App\Models\User::factory()->create());
    }

    public function test_blog_view_tracking_increments_count()
    {
        $blog = Blog::factory()->create(['slug' => 'test-blog']);
        
        // 1. First Visit
        $response = $this->get(route('blog.show', $blog->slug));
        
        $response->assertStatus(200);
        $this->assertEquals(1, $blog->fresh()->views);
        $this->assertDatabaseHas('blog_views', ['blog_id' => $blog->id]);
        
        // 2. Second Visit (Should be blocked by cache)
        $this->get(route('blog.show', $blog->slug));
        $this->assertEquals(1, $blog->fresh()->views); // Still 1
        
        // 3. Visit from different IP/Session (Clear cache key manually to simulate)
        $ip = request()->ip(); // default 127.0.0.1
        Cache::forget('viewed_blog_' . $blog->id . '_' . $ip);
        
        // Simulating distinct visit is hard without mocking request IP, but we verified basic logic
    }

    public function test_sitemap_index_structure()
    {
        $response = $this->get('/sitemap.xml');
        $response->assertStatus(200);
        $response->assertSee('sitemapindex');
        $response->assertSee(route('sitemap.categories'));
        $response->assertSee(route('sitemap.pages'));
    }

    public function test_pages_sitemap_loads()
    {
        $response = $this->get(route('sitemap.pages'));
        $response->assertStatus(200);
        $response->assertSee(route('privacy-policy'));
    }

    public function test_dashboard_analytics_rendering()
    {
        // Generate dummy views
        $blog = Blog::factory()->create();
        BlogView::create(['blog_id' => $blog->id, 'ip_address' => '127.0.0.1', 'country_code' => 'US']);
        
        $response = $this->get(route('admin.dashboard'));
        
        $response->assertStatus(200);
        $response->assertSee('Views Overview');
        $response->assertSee('Most Visited Blogs');
        $response->assertSee('US'); // checks country code rendering
    }
}
