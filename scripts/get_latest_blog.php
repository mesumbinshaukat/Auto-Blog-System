<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$blog = App\Models\Blog::latest()->first();

if (!$blog) {
    echo json_encode(['error' => 'No blogs found'], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$output = [
    'id' => $blog->id,
    'title' => $blog->title,
    'slug' => $blog->slug,
    'url' => url('/blog/' . $blog->slug),
    'category' => optional($blog->category)->name,
    'custom_prompt' => $blog->custom_prompt,
];

echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;
