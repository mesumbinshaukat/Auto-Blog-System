<div class="relative w-full max-w-md" x-data="{ focused: false }">
    <form action="{{ route('search.index') }}" method="GET" class="relative z-50">
        <input 
            name="q"
            value="{{ request('q') }}"
            wire:model.live="query"
            @focus="focused = true"
            @blur="setTimeout(() => focused = false, 200)"
            type="text" 
            class="w-full bg-gray-100 border-none rounded-full py-2 pl-4 pr-10 text-sm focus:ring-2 focus:ring-blue-500 transition-all placeholder-gray-400 text-gray-700"
            placeholder="Search stories..."
            autocomplete="off"
        >
        <button type="submit" class="absolute right-3 top-2.5 text-gray-400 hover:text-blue-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </button>
    </form>

    @if(strlen($query) >= 2)
        <div 
            x-show="focused" 
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            class="absolute top-12 left-0 w-full bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden z-50 ring-1 ring-black ring-opacity-5"
        >
            @if(count($results) > 0)
                <ul class="divide-y divide-gray-50">
                    @foreach($results as $result)
                        <li>
                            <a href="{{ route('blog.show', $result->slug) }}" class="flex items-center gap-3 p-3 hover:bg-blue-50 transition group">
                                <img src="{{ ($result->thumbnail_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($result->thumbnail_path)) ? asset('storage/' . $result->thumbnail_path) : 'https://placehold.co/100x100/e2e8f0/1e293b?text=IMG' }}" 
                                     class="w-12 h-12 object-cover rounded-lg shadow-sm group-hover:shadow-md transition">
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900 group-hover:text-blue-700 leading-tight">{{ $result->title }}</h4>
                                    <span class="text-xs text-gray-400">{{ $result->published_at->format('M d') }}</span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="bg-gray-50 p-3 text-center border-t">
                    <a href="{{ route('search.index', ['q' => $query]) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 uppercase tracking-wide">
                        View All Results &rarr;
                    </a>
                </div>
            @else
                <div class="p-4 text-center text-gray-500 text-sm">
                    No stories found for "<span class="font-bold text-gray-700">{{ $query }}</span>"
                </div>
            @endif
        </div>
    @endif
</div>
