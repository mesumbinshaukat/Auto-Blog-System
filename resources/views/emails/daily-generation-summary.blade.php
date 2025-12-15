@extends('emails.layout')

@section('title', '📊 Daily Generation Summary')

@section('content')
<div class="alert-{{ $failureCount > 0 ? 'warning' : 'success' }}">
    <strong>Daily Blog Generation Complete!</strong> Here's your summary for today.
</div>

<div class="details">
    <div class="details-row">
        <span class="details-label">📅 Date:</span>
        <span class="details-value">{{ now()->format('M d, Y') }}</span>
    </div>
    <div class="details-row">
        <span class="details-label">✅ Successful:</span>
        <span class="details-value"><span class="badge badge-success">{{ $successCount }}</span></span>
    </div>
    <div class="details-row">
        <span class="details-label">❌ Failed:</span>
        <span class="details-value"><span class="badge badge-{{ $failureCount > 0 ? 'danger' : 'success' }}">{{ $failureCount }}</span></span>
    </div>
    <div class="details-row">
        <span class="details-label">📊 Success Rate:</span>
        <span class="details-value">{{ $successCount + $failureCount > 0 ? round(($successCount / ($successCount + $failureCount)) * 100, 1) : 0 }}%</span>
    </div>
</div>

@if($successCount > 0)
<h3>✅ Successfully Generated Blogs</h3>
<ul>
    @foreach($successfulBlogs as $blog)
        <li>
            <strong>{{ $blog['title'] }}</strong> 
            <span class="badge badge-success">{{ $blog['category'] }}</span>
            <br>
            <small style="color: #6c757d;">
                <a href="{{ $blog['url'] }}" style="color: #667eea;">View Post</a> | 
                {{ $blog['word_count'] }} words | 
                Generated at {{ $blog['time'] }}
            </small>
        </li>
    @endforeach
</ul>
@endif

@if($failureCount > 0)
<h3>❌ Failed Generations</h3>
<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
    <thead>
        <tr style="background: #f8f9fa;">
            <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Category</th>
            <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Error</th>
        </tr>
    </thead>
    <tbody>
        @foreach($errors as $error)
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #dee2e6;"><strong>{{ $error['category'] }}</strong></td>
            <td style="padding: 10px; border-bottom: 1px solid #dee2e6; font-size: 12px;">{{ Str::limit($error['error'], 100) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="alert-warning">
    <strong>Action Required:</strong> Review failed generations and retry manually if needed.
</div>
@endif

<div class="alert-info">
    <strong>System Health:</strong> 
    @if($failureCount === 0)
        All systems operating normally. ✅
    @elseif($failureCount <= 2)
        Minor issues detected. Monitor for patterns. ⚠️
    @else
        Multiple failures detected. Investigation recommended. 🚨
    @endif
</div>
@endsection
