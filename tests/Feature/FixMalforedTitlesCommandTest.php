<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FixMalforedTitlesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_fixes_malformed_titles()
    {
        // 1. Create blogs
        $badBlog = Blog::factory()->create(['title' => 'User&rsquo;s Guide']);
        $goodBlog = Blog::factory()->create(['title' => 'Clean Title']);
        $ampBlog = Blog::factory()->create(['title' => 'Tom & Jerry']); // Literal &, typically safe or desired as is if not an entity

        $this->artisan('blog:fix-titles')
             ->expectsOutputToContain('Found 2 potential candidates')
             ->expectsOutputToContain('Scan complete. Fixed 1 titles.')
             ->assertExitCode(0);

        // 3. Verify
        $this->assertDatabaseHas('blogs', [
            'id' => $badBlog->id,
            'title' => 'User’s Guide'
        ]);
        
        $this->assertDatabaseHas('blogs', [
            'id' => $goodBlog->id,
            'title' => 'Clean Title'
        ]);

        $this->assertDatabaseHas('blogs', [
            'id' => $ampBlog->id,
            'title' => 'Tom & Jerry'
        ]);
    }
}
