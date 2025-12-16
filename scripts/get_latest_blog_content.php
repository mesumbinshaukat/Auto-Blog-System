<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$blog = App\Models\Blog::latest()->with('category')->first();

if (!$blog) {
    echo json_encode(['error' => 'No blogs found'], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$output = [
    'title' => $blog->title,
    'slug' => $blog->slug,
    'custom_prompt' => $blog->custom_prompt,
    'category' => optional($blog->category)->name,
    'content' => $blog->content,
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
