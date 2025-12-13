@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Admin Dashboard</h1>
        <div class="space-x-4">
            <a href="{{ route('admin.blogs.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                + New Blog
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-blue-500">
            <div class="text-gray-500 text-sm uppercase">Total Blogs</div>
            <div class="text-3xl font-bold">{{ \App\Models\Blog::count() }}</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-green-500">
            <div class="text-gray-500 text-sm uppercase">Total Views</div>
            <div class="text-3xl font-bold">{{ \App\Models\Blog::sum('views') }}</div>
        </div>
         <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-purple-500">
            <div class="text-gray-500 text-sm uppercase">Manual Generation</div>
            <form action="{{ route('admin.generate') }}" method="POST" class="mt-2 flex space-x-2">
                @csrf
                <select name="category_id" class="text-sm border rounded p-1 w-full">
                    @foreach(\App\Models\Category::all() as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-purple-600 text-white text-xs px-3 py-1 rounded hover:bg-purple-700">Go</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    <!-- Blog Table -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Published</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($blogs as $blog)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900 truncate w-64">{{ $blog->title }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            {{ $blog->category->name }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $blog->published_at ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                            {{ $blog->published_at ? 'Published' : 'Draft' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $blog->published_at ? $blog->published_at->diffForHumans() : '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                        <a href="{{ route('blog.show', $blog->slug) }}" target="_blank" class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                        <a href="{{ route('admin.blogs.edit', $blog->id) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                        <form action="{{ route('admin.blogs.destroy', $blog->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            {{ $blogs->links() }}
        </div>
    </div>
</div>
@endsection
