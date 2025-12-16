<?php
try {
    echo "Starting Manual Simulation...\n";
    $url = "https://imgify.worldoftech.company";
    $prompt = "Write a blog about this tool \"$url\"";
    
    // Resolve service
    $service = app(\App\Services\BlogGeneratorService::class);
    
    // Get a category
    $category = \App\Models\Category::first();
    if (!$category) {
        $category = \App\Models\Category::factory()->create(['name' => 'Tech']);
    }
    
    echo "Category: " . $category->name . "\n";
    echo "Prompt: $prompt\n";
    
    // Mock scraper to avoid actual scraping if needed, but User said "do the curl.exe yourself... manually generate".
    // Actually, user wants REAL generation. So I should let it scrape.
    // Ensure I don't mock locally if I want real result.
    // The Service is resolved from app(), so it has REAL dependencies (unless test environment binding leaks, but tinker is separate).
    
    // Call the method
    // We pass a closure for progress to see what happens
    $blog = $service->generateBlogForCategory($category, function($msg) {
        echo "Progress: $msg\n";
    }, $prompt);
    
    if ($blog) {
        echo "SUCCESS! Blog generated: " . $blog->title . "\n";
        echo "Custom Prompt Saved: " . $blog->custom_prompt . "\n";
    } else {
        echo "FAILED to generate blog.\n";
    }
} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
