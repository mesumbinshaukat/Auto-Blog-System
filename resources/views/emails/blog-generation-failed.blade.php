@extends('emails.layout')

@section('title', '❌ Blog Generation Failed')

@section('content')
<div class="alert-danger">
    <strong>Blog generation failed!</strong> The system encountered an error while trying to generate a blog post.
</div>

<h2>Attempted Topic: {{ $topic }}</h2>

<div class="details">
    <div class="details-row">
        <span class="details-label">⏰ Failed At:</span>
        <span class="details-value">{{ $failedAt }}</span>
    </div>
    <div class="details-row">
        <span class="details-label">🔄 Attempt Number:</span>
        <span class="details-value">{{ $attemptNumber }} of {{ $maxAttempts ?? 5 }}</span>
    </div>
    <div class="details-row">
        <span class="details-label">📁 Category:</span>
        <span class="details-value">{{ $category ?? 'N/A' }}</span>
    </div>
</div>

<h3>❗ Error Details</h3>
<div class="alert-warning">
    <strong>Error Message:</strong><br>
    {{ $errorMessage }}
</div>

@if(!empty($apisAttempted))
<h3>🤖 APIs Attempted</h3>
<ul>
    @foreach($apisAttempted as $api => $status)
        <li>
            <strong>{{ $api }}:</strong> 
            <span class="badge badge-{{ $status === 'failed' ? 'danger' : 'warning' }}">{{ $status }}</span>
        </li>
    @endforeach
</ul>
@endif

@if(!empty($trace))
<h3>🔍 Stack Trace</h3>
<pre>{{ $trace }}</pre>
@endif

<div class="alert-info">
    <strong>Recommended Actions:</strong>
    <ul>
        <li>Check API keys and quotas</li>
        <li>Verify internet connectivity</li>
        <li>Review logs for more details</li>
        @if($attemptNumber >= 3)
        <li><strong>⚠️ Multiple failures detected - manual intervention may be required</strong></li>
        @endif
    </ul>
</div>
@endsection
