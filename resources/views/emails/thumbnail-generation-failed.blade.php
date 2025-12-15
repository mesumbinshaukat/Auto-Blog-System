@extends('emails.layout')

@section('title', '⚠️ Thumbnail Generation Failed')

@section('content')
<div class="alert-warning">
    <strong>Thumbnail generation failed!</strong> The blog post was created successfully, but the thumbnail could not be generated.
</div>

<h2>{{ $blog->title }}</h2>

<div class="details">
    <div class="details-row">
        <span class="details-label">⏰ Failed At:</span>
        <span class="details-value">{{ $failedAt }}</span>
    </div>
    <div class="details-row">
        <span class="details-label">🔄 Fallback Used:</span>
        <span class="details-value">{{ $fallback }}</span>
    </div>
    <div class="details-row">
        <span class="details-label">📁 Category:</span>
        <span class="details-value">{{ $blog->category->name }}</span>
    </div>
</div>

<div style="text-align: center; margin: 20px 0;">
    <a href="{{ $blogUrl }}" class="btn">View Blog Post</a>
</div>

<h3>❗ Error Details</h3>
<div class="alert-danger">
    {{ $errorDetails }}
</div>

@if(!empty($apisAttempted))
<h3>🤖 Thumbnail APIs Attempted</h3>
<ul>
    @foreach($apisAttempted as $api => $status)
        <li><strong>{{ $api }}:</strong> <span class="badge badge-danger">{{ $status }}</span></li>
    @endforeach
</ul>
@endif

<div class="alert-info">
    <strong>Impact:</strong> The blog post is live and functional. The fallback thumbnail ({{ $fallback }}) is being used temporarily.
    <br><br>
    <strong>Recommended Actions:</strong>
    <ul>
        <li>Check HuggingFace and Gemini API quotas</li>
        <li>Verify thumbnail generation service status</li>
        <li>Consider manually uploading a custom thumbnail</li>
    </ul>
</div>
@endsection
