<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Generation Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #e0e0e0;
        }
        .status-badge {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
        }
        .status-success {
            background-color: #4CAF50;
            color: white;
        }
        .status-failed {
            background-color: #f44336;
            color: white;
        }
        .status-duplicate {
            background-color: #ff9800;
            color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #555;
        }
        .thumbnail {
            max-width: 200px;
            height: auto;
            border-radius: 4px;
        }
        .error-section {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .error-title {
            color: #c62828;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .logs-section {
            background-color: #f5f5f5;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .logs-title {
            color: #1976D2;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .log-item {
            padding: 5px 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .log-warning {
            color: #ff6f00;
        }
        .log-error {
            color: #c62828;
            font-weight: bold;
        }
        .stack-trace {
            background-color: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin-top: 10px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }
        .btn:hover {
            background-color: #1976D2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="status-badge status-{{ strtolower($status) === 'success' ? 'success' : (strtolower($status) === 'all topics duplicate' ? 'duplicate' : 'failed') }}">
                {{ $status === 'Success' ? '✅' : '❌' }} {{ $status }}
            </div>
            <h1>Blog Generation Report</h1>
            <p style="color: #666;">{{ $generatedAt }}</p>
        </div>

        <h2>Summary</h2>
        <table>
            <tr>
                <th>Time</th>
                <td>{{ $generatedAt }}</td>
            </tr>
            <tr>
                <th>Title</th>
                <td>
                    @if($blog)
                        <a href="{{ route('blog.show', $blog->slug) }}" class="btn">{{ $blog->title }}</a>
                    @else
                        <span style="color: #f44336;">Failed</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>Thumbnail</th>
                <td>
                    @if($blog && $blog->thumbnail_path)
                        <img src="{{ Storage::url($blog->thumbnail_path) }}" alt="Thumbnail" class="thumbnail">
                    @else
                        <span style="color: #f44336;">{{ $blog ? 'No thumbnail' : 'Failed' }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>Status</th>
                <td><strong>{{ $status }}</strong></td>
            </tr>
            @if($blog)
            <tr>
                <th>Category</th>
                <td>{{ $blog->category->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Word Count</th>
                <td>{{ str_word_count(strip_tags($blog->content)) }} words</td>
            </tr>
            @endif
        </table>

        @if($error || $isDuplicate)
        <div class="error-section">
            <div class="error-title">Error Details</div>
            @if($isDuplicate)
                <p><strong>All Topics Duplicate:</strong> All 10 topic attempts were duplicates. No new blog could be generated.</p>
            @elseif($error)
                @php
                    $errorMsg = $error->getMessage();
                    $errorType = 'General Error';
                    
                    if (str_contains($errorMsg, 'quota') || str_contains($errorMsg, '429')) {
                        if (str_contains($errorMsg, 'Gemini') || str_contains($errorMsg, 'generativelanguage')) {
                            $errorType = 'Gemini API Quota Exceeded';
                        } elseif (str_contains($errorMsg, 'HuggingFace') || str_contains($errorMsg, 'huggingface')) {
                            $errorType = 'HuggingFace API Quota Exceeded';
                        } else {
                            $errorType = 'API Quota Exceeded';
                        }
                    } elseif (str_contains($errorMsg, 'All RSS')) {
                        $errorType = 'All RSS Sources Failed';
                    } elseif (str_contains($errorMsg, 'Thumbnail')) {
                        $errorType = 'Thumbnail Generation Failed';
                    } elseif (str_contains($errorMsg, 'OpenRouter') || str_contains($errorMsg, '404')) {
                        $errorType = 'OpenRouter API Error';
                    }
                @endphp
                <p><strong>{{ $errorType }}:</strong> {{ $errorMsg }}</p>
                
                @if($error->getTraceAsString())
                <details>
                    <summary style="cursor: pointer; color: #1976D2; margin-top: 10px;">View Stack Trace</summary>
                    <div class="stack-trace">{{ $error->getTraceAsString() }}</div>
                </details>
                @endif
            @endif
        </div>
        @endif

        @if(count($logs) > 0)
        <div class="logs-section">
            <div class="logs-title">Generation Logs</div>
            <ul style="list-style: none; padding: 0;">
                @foreach($logs as $log)
                    <li class="log-item {{ str_contains(strtolower($log), 'warning') ? 'log-warning' : (str_contains(strtolower($log), 'error') || str_contains(strtolower($log), 'failed') ? 'log-error' : '') }}">
                        • {{ $log }}
                    </li>
                @endforeach
            </ul>
        </div>
        @endif

        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0; text-align: center; color: #666;">
            <p>Auto-Blog System - Automated Blog Generation Report</p>
            <p style="font-size: 12px;">This is an automated notification. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
