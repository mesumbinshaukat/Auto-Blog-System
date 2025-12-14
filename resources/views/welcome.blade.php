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
                <img src="{{ ($blog->thumbnail_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($blog->thumbnail_path)) ? asset('storage/' . $blog->thumbnail_path) : "https://placehold.co/1200x600/1d4ed8/ffffff?text={$blog->category->name}" }}" class="w-full h-full object-cover opacity-50">
                <div class="absolute bottom-0 left-0 p-8 text-white w-full bg-gradient-to-t from-black to-transparent">
                    <span class="bg-blue-600 text-xs font-bold px-2 py-1 rounded uppercase">{{ $blog->category->name }}</span>
                    <h2 class="text-4xl font-bold mt-2 leading-tight">
                        <a href="{{ route('blog.show', $blog->slug) }}" class="hover:underline">{{ $blog->title }}</a>
                    </h2>
                    <!-- <p class="text-gray-300 mt-2 line-clamp-2">{{ $blog->meta_description }}</p> -->
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



    <!-- Category Sections -->
    <div class="space-y-12 mb-16">
        @foreach($categories as $cat)
            @if($cat->blogs->count() > 0)
                <section>
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">{{ $cat->name }}</h2>
                        <a href="{{ route('category', $cat->slug) }}" class="text-blue-600 text-sm font-semibold hover:underline">View All &rarr;</a>
                    </div>

                    @if($cat->blogs->count() < 3)
                        <!-- Grid Fallback for few items -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($cat->blogs as $blog)
                                <div class="bg-white rounded-lg shadow-sm border overflow-hidden hover:shadow-md transition">
                                    <div class="h-48 overflow-hidden bg-gray-100 relative">
                                        <img src="{{ ($blog->thumbnail_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($blog->thumbnail_path)) ? asset('storage/' . $blog->thumbnail_path) : "https://placehold.co/600x400/e2e8f0/1e293b?text={$cat->name}" }}" 
                                             class="w-full h-full object-cover transition-transform duration-300 hover:scale-105" loading="lazy">
                                    </div>
                                    <div class="p-4">
                                        <h3 class="font-bold text-gray-900 mb-1 leading-tight">
                                            <a href="{{ route('blog.show', $blog->slug) }}">{{ $blog->title }}</a>
                                        </h3>
                                        <span class="text-xs text-gray-500">{{ $blog->published_at->format('M d') }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <!-- Alpine JS Carousel -->
                        <div x-data="{ 
                                scrollAmount: 0, 
                                maxScroll: 0,
                                updateMaxScroll() { this.maxScroll = this.$refs.container.scrollWidth - this.$refs.container.clientWidth },
                                scroll(direction) { 
                                    const step = 320; // card width + gap
                                    if(direction === 'left') this.scrollAmount = Math.max(0, this.scrollAmount - step);
                                    else this.scrollAmount = Math.min(this.maxScroll, this.scrollAmount + step);
                                    this.$refs.container.scrollTo({ left: this.scrollAmount, behavior: 'smooth' });
                                }
                            }" 
                            x-init="updateMaxScroll(); window.addEventListener('resize', () => updateMaxScroll())"
                            class="relative group">
                            
                            <!-- Prev Button -->
                            <button @click="scroll('left')" x-show="scrollAmount > 0" class="absolute -left-4 top-1/2 -translate-y-1/2 z-10 bg-white shadow-lg rounded-full p-2 text-gray-700 hover:text-blue-600 hidden md:block opacity-0 group-hover:opacity-100 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                            </button>

                            <!-- Carousel Container -->
                            <div x-ref="container" class="flex space-x-6 overflow-x-auto scrollbar-hide snap-x snap-mandatory pb-4" style="scroll-behavior: smooth;">
                                @foreach($cat->blogs as $blog)
                                    <div class="flex-none w-72 md:w-80 snap-start">
                                        <div class="bg-white rounded-lg shadow-sm border overflow-hidden hover:shadow-md transition h-full flex flex-col">
                                            <div class="h-40 overflow-hidden bg-gray-100 relative">
                                                <img src="{{ ($blog->thumbnail_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($blog->thumbnail_path)) ? asset('storage/' . $blog->thumbnail_path) : "https://placehold.co/600x400/e2e8f0/1e293b?text={$cat->name}" }}" 
                                                     class="w-full h-full object-cover transition-transform duration-300 hover:scale-105" loading="lazy">
                                                
                                                <!-- Overlay ID for verification -->
                                                <span class="absolute bottom-1 right-1 text-[10px] text-white bg-black/50 px-1 rounded">ID: {{ $blog->id }}</span>
                                            </div>
                                            <div class="p-4 flex-1 flex flex-col justify-between">
                                                <div>
                                                    <h3 class="font-bold text-gray-900 mb-2 leading-tight line-clamp-2">
                                                        <a href="{{ route('blog.show', $blog->slug) }}" class="hover:text-blue-600">{{ $blog->title }}</a>
                                                    </h3>
                                                    <p class="text-xs text-gray-500 line-clamp-2 mb-2">{{ $blog->meta_description }}</p>
                                                </div>
                                                <div class="flex justify-between items-center mt-3 pt-3 border-t text-xs text-gray-400">
                                                    <span>{{ $blog->published_at->format('M d, Y') }}</span>
                                                    <span>{{ ceil(str_word_count(strip_tags($blog->content)) / 200) }} min read</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Next Button -->
                            <button @click="scroll('right')" x-show="scrollAmount < maxScroll" class="absolute -right-4 top-1/2 -translate-y-1/2 z-10 bg-white shadow-lg rounded-full p-2 text-gray-700 hover:text-blue-600 hidden md:block opacity-0 group-hover:opacity-100 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            </button>
                        </div>
                    @endif
                </section>
            @endif
        @endforeach
    </div>

    <!-- Main Feed -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Blog Grid -->
        <div class="lg:col-span-2 space-y-8">
            <h3 class="text-2xl font-bold text-gray-800">Latest Stories</h3>
            @forelse($feed as $blog)
                <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition overflow-hidden flex flex-col md:flex-row h-auto md:h-48">
                    <div class="w-full md:w-1/3 bg-gray-200">
                        <img src="{{ ($blog->thumbnail_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($blog->thumbnail_path)) ? asset('storage/' . $blog->thumbnail_path) : "https://placehold.co/400x300/e2e8f0/1e293b?text={$blog->category->name}" }}" class="w-full h-full object-cover">
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
