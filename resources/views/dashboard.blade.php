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
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-blue-500">
            <div class="text-gray-500 text-sm uppercase">Total Blogs</div>
            <div class="text-3xl font-bold">{{ \App\Models\Blog::count() }}</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-green-500">
            <div class="text-gray-500 text-sm uppercase">Total Views</div>
            <div class="text-3xl font-bold">{{ \App\Models\Blog::sum('views') }}</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-yellow-500">
            <div class="text-gray-500 text-sm uppercase">Unique Visitors</div>
            <div class="text-3xl font-bold">{{ number_format($uniqueVisitors) }}</div>
        </div>
         <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-purple-500">
            <div class="text-gray-500 text-sm uppercase">Manual Gen (Single)</div>
            <div x-data="{ 
                loading: false, 
                progress: 0, 
                statusMessage: '', 
                jobId: null,
                async startGeneration() {
                    this.loading = true;
                    this.progress = 5;
                    this.statusMessage = 'Initializing...';
                    
                    try {
                        let formData = new FormData(this.$refs.genForm);
                        let response = await fetch('{{ route('admin.generate') }}', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        });
                        
                        let data = await response.json();
                        
                        if (data.success) {
                            this.jobId = data.job_id;
                            this.pollStatus();
                        } else {
                            alert('Failed to start job');
                            this.loading = false;
                        }
                    } catch (e) {
                        alert('Error: ' + e.message);
                        this.loading = false;
                    }
                },
                async pollStatus() {
                    if (!this.jobId) return;
                    
                    let interval = setInterval(async () => {
                        try {
                            let res = await fetch(`/admin/blog/status/${this.jobId}`);
                            let status = await res.json();
                            
                            this.progress = status.progress;
                            this.statusMessage = status.message;
                            
                            if (status.status === 'completed') {
                                clearInterval(interval);
                                window.location.reload();
                            } else if (status.status === 'failed') {
                                clearInterval(interval);
                                alert('Generation Failed: ' + status.message);
                                this.loading = false;
                                this.jobId = null;
                            }
                        } catch (e) {
                            console.error('Polling error', e);
                        }
                    }, 2000);
                }
            }">
                <form x-ref="genForm" @submit.prevent="startGeneration" class="mt-2 flex flex-col space-y-2">
                    <div class="flex space-x-2">
                        <select name="category_id" class="text-sm border rounded p-1 w-full flex-grow" :disabled="loading">
                            @foreach(\App\Models\Category::all() as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="bg-purple-600 text-white text-xs px-3 py-1 rounded hover:bg-purple-700 flex items-center disabled:opacity-50 disabled:cursor-not-allowed" :disabled="loading">
                            <span x-show="!loading">Go</span>
                            <span x-show="loading">...</span>
                        </button>
                    </div>
                    
                    <textarea name="custom_prompt" rows="2" class="w-full text-xs border rounded p-1 mt-2 focus:ring-purple-500 focus:border-purple-500" placeholder="Optional: Custom prompt or URL to scrape..." :disabled="loading"></textarea>
                    
                    <div x-show="loading" class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700 mt-2">
                        <div class="bg-purple-600 h-2.5 rounded-full transition-all duration-500" :style="'width: ' + progress + '%'"></div>
                    </div>
                    <p x-show="loading" class="text-xs text-gray-500" x-text="statusMessage"></p>
                </form>
            </div>
        </div>

        <!-- Batch Generation Card -->
        <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-indigo-500">
            <div class="text-gray-500 text-sm uppercase">Batch Gen (5 Blogs)</div>
            <div x-data="{ 
                loading: false, 
                message: '',
                async startBatch() {
                    this.loading = true;
                    this.message = 'Starting batch...';
                    
                    try {
                        let formData = new FormData(this.$refs.batchForm);
                        let response = await fetch('{{ route('admin.generate.batch') }}', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        });
                        
                        let data = await response.json();
                        
                        if (data.success) {
                            this.message = 'Batch started! (Check logs)';
                            setTimeout(() => { window.location.reload() }, 2000);
                        } else {
                            this.message = 'Failed: ' + data.message;
                            this.loading = false;
                        }
                    } catch (e) {
                         this.message = 'Error: ' + e.message;
                         this.loading = false;
                    }
                }
            }">
                <form x-ref="batchForm" @submit.prevent="startBatch" class="mt-2 flex flex-col space-y-2">
                    <input type="hidden" name="count" value="5">
                    <div class="flex space-x-2">
                        <select name="category_id" class="text-sm border rounded p-1 w-full">
                            <option value="">Random Categories</option>
                            @foreach(\App\Models\Category::all() as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="bg-indigo-600 text-white text-xs px-3 py-1 rounded hover:bg-indigo-700 flex items-center disabled:opacity-50" :disabled="loading">
                            <span x-show="!loading">5x</span>
                            <span x-show="loading">...</span>
                        </button>
                    </div>
                    <p x-show="message" class="text-xs text-gray-600 mt-2" x-text="message"></p>
                </form>
            </div>
        </div>
    </div>

    <!-- Analytics Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Main Chart -->
        <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-sm">
            <h3 class="text-lg font-semibold mb-4 text-gray-700">Views Overview (Last 30 Days)</h3>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="viewsChart"></canvas>
            </div>
        </div>

        <!-- Top Stats -->
        <div class="space-y-6">
            <!-- Most Visited -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Most Visited Blogs</h3>
                <ul class="space-y-3">
                    @foreach($mostVisited as $mv)
                    <li class="flex justify-between items-center text-sm">
                        <span class="truncate w-48 text-gray-600" title="{{ $mv->title }}">{{ $mv->title }}</span>
                        <span class="font-bold text-blue-600">{{ number_format($mv->views) }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>

            <!-- Top Countries -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Top Locations</h3>
                <ul class="space-y-3">
                    @php
                        $countryMap = [
                            'XX' => 'Unknown Region',
                            'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia',
                            'DE' => 'Germany', 'FR' => 'France', 'IN' => 'India', 'CN' => 'China', 'JP' => 'Japan',
                            'BR' => 'Brazil', 'RU' => 'Russia', 'ZA' => 'South Africa', 'PK' => 'Pakistan',
                            'ID' => 'Indonesia', 'MX' => 'Mexico', 'ES' => 'Spain', 'IT' => 'Italy', 'NL' => 'Netherlands',
                            'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland', 'KR' => 'South Korea',
                            'TR' => 'Turkey', 'SA' => 'Saudi Arabia', 'AE' => 'United Arab Emirates', 'EG' => 'Egypt',
                            'NG' => 'Nigeria', 'KE' => 'Kenya', 'AR' => 'Argentina', 'CL' => 'Chile', 'CO' => 'Colombia',
                            'PL' => 'Poland', 'UA' => 'Ukraine', 'TH' => 'Thailand', 'VN' => 'Vietnam', 'PH' => 'Philippines',
                            'BD' => 'Bangladesh', 'IR' => 'Iran', 'IQ' => 'Iraq', 'IL' => 'Israel', 'GR' => 'Greece',
                            'PT' => 'Portugal', 'BE' => 'Belgium', 'CH' => 'Switzerland', 'AT' => 'Austria', 'IE' => 'Ireland',
                        ];
                    @endphp
                    @foreach($topCountries as $tc)
                    <li class="flex justify-between items-center text-sm">
                        <div class="flex items-center">
                            @if($tc->country_code && $tc->country_code !== 'XX')
                                <img src="https://flagcdn.com/24x18/{{ strtolower($tc->country_code) }}.png" 
                                     class="mr-2 w-4 h-3 object-cover rounded-sm" 
                                     onerror="this.style.display='none'"> 
                            @else
                                <span class="mr-2 w-4 h-3 flex items-center justify-center bg-gray-200 rounded-sm text-[8px] text-gray-500">?</span>
                            @endif
                            
                            <span class="text-gray-600 ml-1">
                                {{ $countryMap[$tc->country_code] ?? ($tc->country_code ?: 'Unknown') }}
                            </span>
                        </div>
                        <span class="font-bold text-green-600">{{ number_format($tc->total) }}</span>
                    </li>
                    @endforeach
                    @if($topCountries->isEmpty())
                        <li class="text-gray-400 text-sm italic">No location data yet.</li>
                    @endif
                </ul>
                <div class="mt-4 pt-4 border-t border-gray-100">
                     {{ $topCountries->appends(request()->query())->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js Integration -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('viewsChart').getContext('2d');
        const chartData = @json($chartData);
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.map(d => d.date),
                datasets: [{
                    label: 'Daily Views',
                    data: chartData.map(d => d.views),
                    borderColor: 'rgb(79, 70, 229)',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [2, 4] } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
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
