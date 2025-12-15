<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\AIService;
use Illuminate\Support\Facades\Log;

class AIArtifactCleanupTest extends TestCase
{
    protected $aiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiService = new AIService();
    }

    /** @test */
    public function it_removes_robotic_phrases()
    {
        $content = "
            <p>In conclusion, this is a great topic.</p>
            <p>To sum up: it works well.</p>
            <p>Ultimately, we succeed.</p>
            <p>Note: This is AI-generated content.</p>
            <p>Real content starts here.</p>
        ";
        
        $cleaned = $this->aiService->cleanupAIArtifacts($content, 'Topic');
        
        $this->assertStringNotContainsString('In conclusion', $cleaned);
        $this->assertStringNotContainsString('To sum up', $cleaned);
        $this->assertStringNotContainsString('Ultimately', $cleaned);
        $this->assertStringNotContainsString('Note: This is AI-generated', $cleaned);
        $this->assertStringContainsString('Real content starts here', $cleaned);
    }

    /** @test */
    public function it_removes_repetitive_bold_topic_starts()
    {
        $topic = "Game Design";
        $content = "
            <p><strong>Game Design</strong> is essential for fun.</p>
            <p><strong>Game design</strong> involves mechanics.</p>
            <p>Some random text <strong>Game Design</strong> middle.</p>
        ";
        
        $cleaned = $this->aiService->cleanupAIArtifacts($content, $topic);
        
        // Assert bold tag is removed from start
        // "Game Design is essential..." (no strong)
        $this->assertStringNotContainsString('<p><strong>Game Design</strong> is essential', $cleaned);
        // Should still contain the text
        $this->assertStringContainsString('Game Design is essential', $cleaned);
        
        // Assert middle bold is preserved (unless limit hit, but count is low here)
        $this->assertStringContainsString('<strong>Game Design</strong>', $cleaned);
    }

    /** @test */
    public function it_limits_excessive_bolding()
    {
        $topic = "Testing";
        // Create content with 10 bolds in a short text (limit is ~5)
        $content = "<p>";
        for ($i = 0; $i < 10; $i++) {
            $content .= "This is <strong>bold $i</strong> text. ";
        }
        $content .= "</p>";
        
        $cleaned = $this->aiService->cleanupAIArtifacts($content, $topic);
        
        // Count strong tags
        $count = substr_count($cleaned, '<strong>');
        
        // Should be <= 6 (5 base + small word count allowance)
        $this->assertLessThanOrEqual(6, $count);
        // Text should remain
        $this->assertStringContainsString('bold 9', $cleaned);
    }

    /** @test */
    public function it_preserves_html_structure_like_links()
    {
        $content = '<p>Check <a href="https://example.com">this link</a> out.</p>';
        $cleaned = $this->aiService->cleanupAIArtifacts($content, 'Topic');
        
        $this->assertStringContainsString('<a href="https://example.com">this link</a>', $cleaned);
    }
}
