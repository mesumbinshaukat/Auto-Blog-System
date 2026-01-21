<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Listing last 20 blogs:\n";
$blogs = App\Models\Blog::latest()->take(20)->get();
foreach ($blogs as $b) {
    echo "- ID: {$b->id} | SLUG: {$b->slug} | TITLE: {$b->title}\n";
}
