<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <title>{{ $meta_title ?? config('app.name', 'Auto Blog System') }}</title>
    <meta name="description" content="{{ $meta_description ?? 'Automated Tech Blogs generated daily.' }}">
    
    <!-- Open Graph -->
    <meta property="og:title" content="{{ $meta_title ?? config('app.name') }}">
    <meta property="og:description" content="{{ $meta_description ?? 'Automated Tech Blogs generated daily.' }}">
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $og_image ?? 'https://source.unsplash.com/random/1200x630?tech' }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @livewireStyles
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900">
    <div class="min-h-screen">
        @if (Route::has('login'))
            <div class="fixed top-0 right-0 p-6 text-right z-50">
                @auth
                    <a href="{{ url('/admin') }}" class="font-semibold text-gray-600 hover:text-gray-900">Admin</a>
                    <form method="POST" action="{{ route('logout') }}" class="inline ml-4">
                        @csrf
                        <button type="submit" class="font-semibold text-gray-600 hover:text-gray-900">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="font-semibold text-gray-600 hover:text-gray-900">Log in</a>
                @endauth
            </div>
        @endif

        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex flex-wrap justify-between items-center gap-4">
                <a href="/" class="flex items-center gap-3 hover:opacity-80 transition">
                    <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" class="h-12 w-auto">
                    <span class="text-3xl font-bold tracking-tight text-gray-900">{{ config('app.name', 'Auto Blog') }}</span>
                </a>
                <nav class="flex flex-wrap gap-x-6 gap-y-2">
                    @foreach(\App\Models\Category::all() as $cat)
                        <a href="{{ route('category', $cat->slug) }}" class="text-gray-600 hover:text-blue-600 uppercase text-sm font-bold whitespace-nowrap">{{ $cat->name }}</a>
                    @endforeach
                </nav>
            </div>
        </header>

        <main class="py-6">
            @yield('content')
        </main>
        
        <footer class="bg-gray-900 text-white mt-12 pt-16 pb-8 border-t border-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
                    <!-- Brand -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2">
                             <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" class="h-8 w-auto brightness-0 invert">
                        </div>
                        <p class="text-gray-400 text-sm leading-relaxed">
                            Automated Tech Blogs generated daily. Stay ahead of the curve with the latest in AI, Technology, and Innovation.
                        </p>
                    </div>

                    <!-- Quick Links -->
                    <div>
                        <h3 class="text-lg font-bold mb-4 text-white">Explore</h3>
                        <ul class="space-y-2 text-gray-400">
                            <li><a href="/" class="hover:text-blue-400 transition">Home</a></li>
                            @foreach(\App\Models\Category::take(4)->get() as $cat)
                                <li><a href="{{ route('category', $cat->slug) }}" class="hover:text-blue-400 transition">{{ $cat->name }}</a></li>
                            @endforeach
                        </ul>
                    </div>

                    <!-- Legal -->
                    <div>
                        <h3 class="text-lg font-bold mb-4 text-white">Legal</h3>
                        <ul class="space-y-2 text-gray-400">
                            <li><a href="{{ route('privacy-policy') }}" class="hover:text-blue-400 transition">Privacy Policy</a></li>
                            <li><a href="{{ route('terms-conditions') }}" class="hover:text-blue-400 transition">Terms & Conditions</a></li>
                        </ul>
                    </div>

                    <!-- Newsletter/Social -->
                    <div>
                        <h3 class="text-lg font-bold mb-4 text-white">Connect</h3>
                        <div class="flex space-x-4">
                            <!-- Social Icons -->
                            <a href="#" class="text-gray-400 hover:text-white transition">
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/></svg>
                            </a>
                            <a href="#" class="text-gray-400 hover:text-white transition">
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.85-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center text-sm text-gray-500">
                    <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                    <p class="mt-2 md:mt-0">Designed with <span class="text-red-500">&hearts;</span> by WorldOfTech</p>
                </div>
            </div>
        </footer>
    </div>
    
    @if(View::exists('cookie-consent::index'))
        @include('cookie-consent::index')
    @endif
    @livewireScripts
</body>
</html>
