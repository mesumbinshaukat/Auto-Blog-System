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
    Route::get('/blog/status/{jobId}', [AdminController::class, 'checkJobStatus'])->name('blog.status');
});

// Legal Pages
Route::get('/privacy-policy', function () {
    return view('legal.privacy-policy');
})->name('privacy-policy');

Route::get('/terms-conditions', function () {
    return view('legal.terms-conditions');
})->name('terms-conditions');

// Sitemap
Route::get('/sitemap.xml', function () {
    $blogs = \App\Models\Blog::latest()->get();
    return response()->view('sitemap', compact('blogs'))->header('Content-Type', 'text/xml');
});
