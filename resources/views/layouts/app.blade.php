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
                    @if(file_exists(public_path('images/logo.webp')))
                        <img src="{{ asset('images/logo.webp') }}" alt="{{ config('app.name') }}" class="h-10 w-auto">
                    @endif
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
        
        <footer class="bg-white border-t mt-12 py-8">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                    <div class="text-center md:text-left text-gray-500">
                        &copy; {{ date('Y') }} Auto Blog System. All rights reserved.
                    </div>
                    <div class="flex space-x-6">
                        <a href="{{ route('privacy-policy') }}" class="text-gray-600 hover:text-blue-600">Privacy Policy</a>
                        <a href="{{ route('terms-conditions') }}" class="text-gray-600 hover:text-blue-600">Terms & Conditions</a>
                    </div>
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
