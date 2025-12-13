<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Blog;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BlogFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_loads()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_blog_detail_page()
    {
        $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);
        $blog = Blog::create([
            'title' => 'Test Blog',
            'slug' => 'test-blog',
            'content' => '<h1>Header</h1><p>Content</p>',
            'category_id' => $category->id,
            'published_at' => now(),
        ]);

        $response = $this->get('/blog/test-blog');
        $response->assertStatus(200);
        $response->assertSee('Test Blog');
    }

    public function test_admin_access_restricted()
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_access_dashboard()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(200);
    }

    public function test_admin_can_create_blog()
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);

        $response = $this->actingAs($user)->post('/admin/blogs', [
            'title' => 'New Blog',
            'content' => 'Content',
            'category_id' => $category->id
        ]);

        $response->assertRedirect('/admin');
        $this->assertDatabaseHas('blogs', ['title' => 'New Blog']);
    }
}
