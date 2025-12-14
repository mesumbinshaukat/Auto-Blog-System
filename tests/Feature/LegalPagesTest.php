<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Blog;

class LegalPagesTest extends TestCase
{
    public function test_privacy_policy_page_loads()
    {
        $response = $this->get('/privacy-policy');

        $response->assertStatus(200);
        $response->assertSee('Privacy Policy');
        $response->assertSee('Last updated');
    }

    public function test_terms_conditions_page_loads()
    {
        $response = $this->get('/terms-conditions');

        $response->assertStatus(200);
        $response->assertSee('Terms and Conditions');
        $response->assertSee('Acceptance of Terms');
    }

    public function test_footer_contains_legal_links()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee(route('privacy-policy'));
        $response->assertSee(route('terms-conditions'));
    }
}
