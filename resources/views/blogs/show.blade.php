@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        <!-- Main Content -->
        <article class="lg:col-span-3 bg-white p-8 rounded-lg shadow-sm">
            <!-- Header -->
            <header class="mb-8">
                <div class="flex items-center space-x-2 text-sm text-gray-500 mb-4">
                    <a href="{{ route('category', $blog->category->slug) }}" class="text-blue-600 font-bold uppercase hover:underline">{{ $blog->category->name }}</a>
                    <span>&bull;</span>
                    <time datetime="{{ $blog->published_at }}">{{ $blog->published_at->format('F d, Y') }}</time>
                    <span>&bull;</span>
                    <span>{{ ceil(str_word_count(strip_tags($blog->content)) / 200) }} min read</span>
                </div>
                
                <h1 class="text-4xl font-extrabold text-gray-900 leading-tight mb-4">{{ $blog->title }}</h1>
                
                <!-- Share Buttons (Mock) -->
                <div class="flex space-x-4">
                    <button class="text-gray-400 hover:text-blue-600"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg></button>
                    <button class="text-gray-400 hover:text-blue-800"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg></button>
                </div>
            </header>

            <!-- Mobile TOC -->
            <div class="lg:hidden mb-8 bg-gray-50 p-4 rounded border">
                <h3 class="font-bold text-gray-700 mb-2">Table of Contents</h3>
                <ul class="space-y-1 text-sm">
                    @foreach($blog->table_of_contents as $item)
                        <li class="pl-{{ ($item['level'] - 2) * 4 }}">
                            <a href="#{{ $item['id'] }}" class="text-blue-600 hover:underline">{{ $item['title'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <!-- Content -->
            <div class="prose prose-lg max-w-none prose-blue leading-relaxed space-y-4" style="line-height: 1.8;">
                <style>
                    .prose p { margin-bottom: 2rem; line-height: 1.8; }
                    .prose h2 { margin-top: 3rem; margin-bottom: 1.5rem; font-size: 1.875rem; font-weight: 700; color: #111827; }
                    .prose h3 { margin-top: 2.5rem; margin-bottom: 1.25rem; font-size: 1.5rem; font-weight: 600; color: #374151; }
                    .prose ul, .prose ol { margin-left: 1.5rem; margin-bottom: 2rem; }
                    .prose ul { list-style-type: disc; }
                    .prose ol { list-style-type: decimal; }
                    
                    /* Enhanced Table Styling */
                    .prose table { width: 100%; border-collapse: collapse; margin: 2rem 0; border: 1px solid #d1d5db; overflow: hidden; border-radius: 0.5rem; }
                    .prose table th { background-color: #3b82f6; color: white; padding: 1rem; text-align: left; font-weight: 600; }
                    .prose table td { padding: 1rem; border-bottom: 1px solid #d1d5db; color: #4b5563; }
                    .prose table tbody tr:nth-child(odd) { background-color: #f9fafb; }
                    .prose table tbody tr:hover { background-color: #f3f4f6; }
                    
                    /* Comparison Table Specifics */
                    .comparison-table { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
                </style>
                {!! $blog->content !!}
            </div>

            <!-- Tags -->
            @if($blog->tags_json)
                <div class="mt-8 pt-6 border-t">
                    <div class="flex flex-wrap gap-2">
                        @foreach($blog->tags_json as $tag)
                            <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-sm">#{{ $tag }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
        </article>

        <!-- Sidebar -->
        <aside class="space-y-8">
            <!-- Desktop TOC -->
            <div class="hidden lg:block bg-white p-6 rounded-lg shadow-sm sticky top-6">
                <h4 class="text-lg font-bold mb-4 border-b pb-2">Table of Contents</h4>
                <nav>
                    <ul class="space-y-2 text-sm">
                        @foreach($blog->table_of_contents as $item)
                            <li class="pl-{{ ($item['level'] - 2) * 4 }}">
                                <a href="#{{ $item['id'] }}" class="text-gray-600 hover:text-blue-600 transition block border-l-2 border-transparent hover:border-blue-500 pl-2">
                                    {{ $item['title'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </div>

            <!-- Related -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h4 class="text-lg font-bold mb-4 border-b pb-2">Related Posts</h4>
                <div class="space-y-4">
                    @foreach($related as $post)
                        <div>
                            <span class="text-xs text-gray-400 block mb-1">{{ $post->published_at->format('M d') }}</span>
                            <a href="{{ route('blog.show', $post->slug) }}" class="font-medium text-gray-800 hover:text-blue-600 leading-snug block">
                                {{ $post->title }}
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </aside>

    </div>
</div>
@endsection
