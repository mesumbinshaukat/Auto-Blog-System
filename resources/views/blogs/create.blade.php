@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Create Blog Post</h1>
        <a href="{{ route('admin.dashboard') }}" class="text-gray-600 hover:text-gray-900">Cancel</a>
    </div>

    <div class="bg-white shadow rounded-lg p-6">
        <form action="{{ route('admin.blogs.store') }}" method="POST">
            @csrf
            
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Title</label>
                <input type="text" name="title" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Category</label>
                <select name="category_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Content (HTML allowed)</label>
                <textarea name="content" rows="15" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline font-mono"></textarea>
                <p class="text-xs text-gray-500 mt-1">Use HTML tags for formatting. Headings H2 and H3 will generate the Table of Contents.</p>
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded focus:outline-none focus:shadow-outline">
                    Create Blog
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
