@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    
    @isset($category)
        <h1 class="text-3xl font-bold mb-8 text-gray-800 border-b pb-4">{{ $category->name }} Posts</h1>
    @endisset

    @if(!isset($category) && $latest->count() > 0)
    <!-- Carousel / Featured Section -->
    <div x-data="{ activeSlide: 0, slides: {{ $latest->count() }} }" class="relative mb-12 rounded-xl overflow-hidden shadow-2xl h-96">
        @foreach($latest as $index => $blog)
            <div x-show="activeSlide === {{ $index }}" class="absolute inset-0 transition-opacity duration-500 ease-in-out bg-gray-900">
                <img src="https://placehold.co/1200x600/1d4ed8/ffffff?text={{ $blog->category->name }}" class="w-full h-full object-cover opacity-50">
                <div class="absolute bottom-0 left-0 p-8 text-white w-full bg-gradient-to-t from-black to-transparent">
                    <span class="bg-blue-600 text-xs font-bold px-2 py-1 rounded uppercase">{{ $blog->category->name }}</span>
                    <h2 class="text-4xl font-bold mt-2 leading-tight">
                        <a href="{{ route('blog.show', $blog->slug) }}" class="hover:underline">{{ $blog->title }}</a>
                    </h2>
                    <p class="text-gray-300 mt-2 line-clamp-2">{{ $blog->meta_description }}</p>
                </div>
            </div>
        @endforeach
        
        <!-- Controls -->
        <div class="absolute bottom-4 right-4 flex space-x-2">
            @foreach($latest as $index => $blog)
                <button @click="activeSlide = {{ $index }}" :class="{'bg-blue-500': activeSlide === {{ $index }}, 'bg-gray-400': activeSlide !== {{ $index }}}" class="w-3 h-3 rounded-full"></button>
            @endforeach
        </div>
        
        <!-- Timer -->
        <div x-init="setInterval(() => { activeSlide = (activeSlide + 1) % slides }, 5000)"></div>
    </div>
    @endif

    <!-- Main Feed -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Blog Grid -->
        <div class="lg:col-span-2 space-y-8">
            <h3 class="text-2xl font-bold text-gray-800">Latest Stories</h3>
            @forelse($feed as $blog)
                <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition overflow-hidden flex flex-col md:flex-row h-auto md:h-48">
                    <div class="w-full md:w-1/3 bg-gray-200">
                        <img src="https://placehold.co/400x300/e2e8f0/1e293b?text={{ $blog->category->name }}" class="w-full h-full object-cover">
                    </div>
                    <div class="p-6 w-full md:w-2/3 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                                <span class="text-blue-600 font-bold uppercase">{{ $blog->category->name }}</span>
                                <span>&bull;</span>
                                <span>{{ $blog->published_at->format('M d, Y') }}</span>
                            </div>
                            <h2 class="text-xl font-bold text-gray-900 leading-tight">
                                <a href="{{ route('blog.show', $blog->slug) }}" class="hover:text-blue-600 transition">{{ $blog->title }}</a>
                            </h2>
                            <p class="text-gray-600 mt-2 line-clamp-2 text-sm">{{ $blog->meta_description }}</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-12 text-gray-500">
                    No blogs found. Check back later!
                </div>
            @endforelse

            <div class="mt-8">
                {{ $feed->links() }}
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="space-y-8">
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h4 class="text-lg font-bold mb-4 border-b pb-2">Categories</h4>
                <ul class="space-y-2">
                    @foreach($categories as $cat)
                        <li>
                            <a href="{{ route('category', $cat->slug) }}" class="flex justify-between text-gray-700 hover:text-blue-600">
                                <span>{{ $cat->name }}</span>
                                <span class="bg-gray-100 text-xs px-2 py-1 rounded-full">{{ $cat->blogs()->count() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>
    </div>
</div>
@endsection
