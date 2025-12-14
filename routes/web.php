<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SimpleAuthController;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/category/{slug}', [HomeController::class, 'category'])->name('category');
Route::get('/blog/{slug}', [HomeController::class, 'show'])->name('blog.show');

// Auth Routes
Route::middleware('guest')->group(function () {
    Route::get('login', [SimpleAuthController::class, 'create'])->name('login');
    Route::post('login', [SimpleAuthController::class, 'store']);
});

Route::post('logout', [SimpleAuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Admin Routes
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('dashboard');
    Route::get('/blogs/create', [AdminController::class, 'create'])->name('blogs.create');
    Route::post('/blogs', [AdminController::class, 'store'])->name('blogs.store');
    Route::get('/blogs/{blog}/edit', [AdminController::class, 'edit'])->name('blogs.edit');
    Route::put('/blogs/{blog}', [AdminController::class, 'update'])->name('blogs.update');
    Route::delete('/blogs/{blog}', [AdminController::class, 'destroy'])->name('blogs.destroy');
    
    // Manual generation trigger
    Route::post('/generate', [AdminController::class, 'generate'])->name('generate');
    Route::post('/generate-batch', [AdminController::class, 'generateMultiple'])->name('generate.batch');
    Route::get('/blog/status/{jobId}', [AdminController::class, 'checkJobStatus'])->name('blog.status');
});

// Legal Pages
Route::get('/privacy-policy', function () {
    return view('legal.privacy-policy');
})->name('privacy-policy');

Route::get('/terms-conditions', function () {
    return view('legal.terms-conditions');
})->name('terms-conditions');

// Sitemap Routes
Route::controller(\App\Http\Controllers\SitemapController::class)->group(function () {
    Route::get('/sitemap.xml', 'index')->name('sitemap.index');
    Route::get('/sitemap/pages.xml', 'pages')->name('sitemap.pages');
    Route::get('/sitemap/categories.xml', 'categories')->name('sitemap.categories');
    Route::get('/sitemap/blogs-{page?}.xml', 'blogs')->name('sitemap.blogs');
});

// Manual Trigger for "Poor Man's Cron" Scheduler
Route::get('/trigger-scheduler', function () {
    // Force run daily scheduler logic 
    // We simulate it by clearing cache and dispatching
    \Illuminate\Support\Facades\Cache::forget('last_daily_run');
    \Illuminate\Support\Facades\Cache::forget('daily_scheduler_lock');
    
    $job = new \App\Jobs\GenerateDailyBlogs();
    $job->handle();
    
    return response()->json([
        'status' => 'success', 
        'message' => 'Scheduler triggered manually. 5 blogs should be queued.',
        'time' => now()->toDateTimeString()
    ]);
});
