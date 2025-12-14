@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-3xl font-bold text-gray-900">
            Search Results for "<span class="text-blue-600">{{ $query }}</span>"
        </h1>
        <span class="text-gray-500">{{ $blogs->total() }} results found</span>
    </div>

    @if($blogs->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($blogs as $blog)
                <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition overflow-hidden h-full flex flex-col">
                    <div class="h-48 overflow-hidden bg-gray-100 relative">
                        <img src="{{ ($blog->thumbnail_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($blog->thumbnail_path)) ? asset('storage/' . $blog->thumbnail_path) : "https://placehold.co/600x400/e2e8f0/1e293b?text=Result" }}" 
                             class="w-full h-full object-cover transition-transform duration-300 hover:scale-105" loading="lazy">
                    </div>
                    <div class="p-6 flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                                <span class="text-blue-600 font-bold uppercase">{{ $blog->category->name }}</span>
                                <span>&bull;</span>
                                <span>{{ $blog->published_at->format('M d, Y') }}</span>
                            </div>
                            <h2 class="text-xl font-bold text-gray-900 leading-tight mb-2">
                                <a href="{{ route('blog.show', $blog->slug) }}" class="hover:text-blue-600 transition">{{ $blog->title }}</a>
                            </h2>
                            <p class="text-gray-600 text-sm line-clamp-3">{{ $blog->meta_description }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $blogs->links() }}
        </div>
    @else
        <div class="text-center py-20 bg-white rounded-lg shadow-sm">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No stories found</h3>
            <p class="mt-1 text-sm text-gray-500">We couldn't find anything matching your search. Try different keywords.</p>
            <div class="mt-6">
                <a href="{{ route('home') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <!-- Heroicon name: solid/arrow-left -->
                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                    </svg>
                    Back to Home
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
