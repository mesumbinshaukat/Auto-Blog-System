<div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        @foreach($feed as $blog)
            <div wire:key="feed-{{ $blog->id }}" class="bg-white rounded-lg shadow-sm hover:shadow-md transition overflow-hidden flex flex-col md:flex-row h-auto md:h-48 group">
                <div class="w-full md:w-1/3 bg-gray-200 relative overflow-hidden">
                    <img src="{{ ($blog->thumbnail_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($blog->thumbnail_path)) ? asset('storage/' . $blog->thumbnail_path) : "https://placehold.co/400x300/e2e8f0/1e293b?text={$blog->category->name}" }}" 
                         class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" 
                         loading="lazy"
                         decoding="async">
                </div>
                <div class="p-6 w-full md:w-2/3 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                            <a href="{{ route('category', $blog->category->slug) }}" class="text-blue-600 font-bold uppercase hover:underline">{{ $blog->category->name }}</a>
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
        @endforeach
    </div>

    @if($hasMore)
        <div x-intersect.full="$wire.loadMore()" class="py-8 text-center flex justify-center">
            <div wire:loading>
                <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <div wire:loading.remove>
                <button wire:click="loadMore" class="text-gray-500 hover:text-blue-600 font-medium text-sm">
                    Scroll for more stories...
                </button>
            </div>
        </div>
    @else
        <div class="text-center py-12 text-gray-500">
            You've reached the end!
        </div>
    @endif
</div>
