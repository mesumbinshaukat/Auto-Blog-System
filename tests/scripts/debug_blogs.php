<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$slugs = [
    'hassett-pivots-to-possible-trump-cards-amid-credit-card-interest-rate-battle-with-banks-1768915237',
    'can-2025-brahim-diaz-breathes-a-huge-sigh-of-relief-1768996073',
    'chinas-ai-boyfriend-business-is-taking-on-a-life-of-its-own-1768914311'
];

foreach ($slugs as $slug) {
    echo "Processing Slug: $slug\n";
    $blog = App\Models\Blog::where('slug', $slug)->first();
    if ($blog) {
        echo "Title: " . $blog->title . "\n";
        echo "Content Preview (first 1000 chars):\n";
        echo substr(strip_tags($blog->content), 0, 1000) . "\n";
        echo "Check for URLs in content:\n";
        preg_match_all('/(https?:\/\/|\/\/)[^\s]+/', $blog->content, $matches);
        print_r($matches[0]);
        echo "\n" . str_repeat("=", 50) . "\n";
    } else {
        echo "Blog not found for slug: $slug\n";
    }
}
