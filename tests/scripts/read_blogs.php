<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$slugs = [
    'hassett-pivots',
    'brahim-diaz',
    'chinas-ai-boyfriend'
];

foreach ($slugs as $s) {
    echo "Searching for slug like: $s\n";
    $blog = App\Models\Blog::where('slug', 'like', '%' . $s . '%')->first();
    if ($blog) {
        echo "MATCHED SLUG: " . $blog->slug . "\n";
        echo "TITLE: " . $blog->title . "\n";
        echo "CREATED AT: " . $blog->created_at . "\n";
        echo "CONTENT PREVIEW:\n" . substr(strip_tags($blog->content), 0, 1500) . "\n";
        echo "RAW URLs FOUND IN CONTENT:\n";
        preg_match_all('/(https?:\/\/|\/\/)[^\s"\'<>]+/i', $blog->content, $matches);
        print_r(array_unique($matches[0]));
        echo "\n" . str_repeat("-", 80) . "\n";
    } else {
        echo "NOT FOUND: $s\n";
    }
}
