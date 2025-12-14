<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\BlogGeneratorService;
use App\Models\Category;
use Illuminate\Support\Facades\Log;

try {
    echo "Starting Enhanced Blog Generation Test...\n";

    // 1. Ensure Category exists
    $category = Category::firstOrCreate(['name' => 'Technology', 'slug' => 'technology']);
    echo "Using Category: {$category->name}\n";

    // 2. Instantiate Service
    $generator = app(BlogGeneratorService::class);
    echo "Service Instantiated.\n";

    // 3. Run Generation with Progress Callback
    echo "Generating Blog... (This may take 1-3 minutes)\n";
    $blog = $generator->generateBlogForCategory($category, function($status, $progress) {
        echo "[$progress%] $status\n";
    });

    if ($blog) {
        echo "\nSUCCESS! Blog Generated:\n";
        echo "Title: {$blog->title}\n";
        echo "Slug: {$blog->slug}\n";
        echo "Thumbnail: " . ($blog->thumbnail_path ?? 'None') . "\n";
        echo "Word Count: " . str_word_count(strip_tags($blog->content)) . "\n";
    } else {
        echo "\nFAILED: Blog generation returned null.\n";
    }

} catch (\Exception $e) {
    echo "\nCRITICAL ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
