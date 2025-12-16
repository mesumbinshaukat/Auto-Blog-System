<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$blog = App\Models\Blog::latest()->first();
if (!$blog) {
    echo "No blogs found" . PHP_EOL;
    exit(0);
}

$blogText = strip_tags($blog->content);

$imgifySnapshot = file_exists(storage_path('app/imgify_snapshot.txt'))
    ? file_get_contents(storage_path('app/imgify_snapshot.txt'))
    : '';

$result = [
    'blog_slug' => $blog->slug,
    'blog_title' => $blog->title,
    'prompt' => $blog->custom_prompt,
    'blog_highlights' => substr($blogText, 0, 1000),
    'mentions_imgify' => stripos($blogText, 'imgify') !== false,
    'mentions_compression' => stripos($blogText, 'compress') !== false,
    'mentions_background_removal' => stripos($blogText, 'background') !== false,
    'mentions_conversion' => stripos($blogText, 'convert') !== false,
    'has_competitor_section' => stripos($blogText, 'alternative') !== false || stripos($blogText, 'competitor') !== false,
    'snapshot_excerpt' => substr($imgifySnapshot, 0, 500)
];

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
