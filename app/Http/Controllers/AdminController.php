<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Category;
use App\Services\BlogGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function index()
    {
        $blogs = Blog::latest()->paginate(20);
        return view('dashboard', compact('blogs'));
    }

    public function create()
    {
        $categories = Category::all();
        return view('blogs.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'content' => 'required',
            'category_id' => 'required|exists:categories,id',
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $validated['published_at'] = now();
        
        Blog::create($validated);

        return redirect()->route('admin.dashboard')->with('success', 'Blog created successfully.');
    }

    public function edit(Blog $blog)
    {
        $categories = Category::all();
        return view('blogs.edit', compact('blog', 'categories'));
    }

    public function update(Request $request, Blog $blog)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'content' => 'required',
            'category_id' => 'required|exists:categories,id',
        ]);

        $blog->update($validated);

        return redirect()->route('admin.dashboard')->with('success', 'Blog updated.');
    }

    public function destroy(Blog $blog)
    {
        $blog->delete();
        return back()->with('success', 'Blog deleted.');
    }

    public function generate(Request $request)
    {
        $request->validate(['category_id' => 'required|exists:categories,id']);
        
        $jobId = Str::uuid()->toString();
        
        Log::info("Dispatching GenerateBlogJob for Category {$request->category_id} with ID: $jobId");
        
        // Dispatch async job
        \App\Jobs\GenerateBlogJob::dispatch($request->category_id, $jobId);

        // Trigger queue worker to process the job immediately
        $this->triggerQueueWorker();

        return response()->json([
            'success' => true, 
            'job_id' => $jobId,
            'message' => 'Job started'
        ]);
    }

    public function generateMultiple(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'count' => 'integer|min:1|max:5'
        ]);

        $count = $validated['count'] ?? 5;
        $categoryId = $validated['category_id'] ?? null;
        
        // If no category specified, pick random ones or cycle
        $jobs = [];

        for ($i = 0; $i < $count; $i++) {
            $catId = $categoryId;
            if (!$catId) {
                $catId = Category::inRandomOrder()->value('id');
            }

            $jobId = Str::uuid()->toString();
            \App\Jobs\GenerateBlogJob::dispatch($catId, $jobId);
            $jobs[] = $jobId;
        }

        // Trigger queue worker to process all jobs
        $this->triggerQueueWorker();

        return response()->json([
            'success' => true,
            'job_ids' => $jobs,
            'message' => "Started generation of $count blogs."
        ]);
    }

    public function checkJobStatus($jobId)
    {
        $status = \Illuminate\Support\Facades\Cache::get("blog_job_{$jobId}");

        if (!$status) {
            return response()->json(['status' => 'pending', 'progress' => 0, 'message' => 'Initializing...']);
        }

        return response()->json($status);
    }

    /**
     * Trigger queue worker to process pending jobs
     * Uses cache-based locking to prevent multiple simultaneous workers
     */
    protected function triggerQueueWorker()
    {
        // Check if a worker is already running using cache lock
        $lockKey = 'queue_worker_running';
        
        if (\Illuminate\Support\Facades\Cache::has($lockKey)) {
            Log::info('Queue worker already running, skipping trigger');
            return;
        }

        // Set lock for 5 minutes (longer than typical job duration)
        \Illuminate\Support\Facades\Cache::put($lockKey, true, 300);

        try {
            Log::info('Triggering queue worker in background');
            
            // Trigger queue worker in background
            // This will process all pending jobs until queue is empty
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows
                pclose(popen('start /B php ' . base_path('artisan') . ' queue:work --stop-when-empty --tries=3 --timeout=300 > NUL 2>&1', 'r'));
            } else {
                // Linux/Unix
                exec('php ' . base_path('artisan') . ' queue:work --stop-when-empty --tries=3 --timeout=300 > /dev/null 2>&1 &');
            }
            
            Log::info('Queue worker triggered successfully');
        } catch (\Exception $e) {
            Log::error('Failed to trigger queue worker: ' . $e->getMessage());
            // Release lock on error
            \Illuminate\Support\Facades\Cache::forget($lockKey);
        }
    }
}
