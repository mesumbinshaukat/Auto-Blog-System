@extends('emails.layout')

@section('title', '✅ Blog Generated Successfully')

@section('content')
<div class="alert-success">
    <strong>Great news!</strong> A new blog post has been successfully generated and published.
</div>

<h2>{{ $blog->title }}</h2>

<div class="details">
    <div class="details-row">
        <span class="details-label">📅 Published At:</span>
        <span class="details-value">{{ $blog->published_at->format('M d, Y H:i:s') }}</span>
    </div>
    <div class="details-row">
        <span class="details-label">📁 Category:</span>
        <span class="details-value">{{ $blog->category->name }}</span>
    </div>
    <div class="details-row">
        <span class="details-label">📝 Word Count:</span>
        <span class="details-value">{{ $wordCount }} words</span>
    </div>
    <div class="details-row">
        <span class="details-label">⏱️ Generation Time:</span>
        <span class="details-value">{{ $generationTime }}</span>
    </div>
    @if(!empty($apisUsed))
    <div class="details-row">
        <span class="details-label">🤖 APIs Used:</span>
        <span class="details-value">{{ implode(', ', $apisUsed) }}</span>
    </div>
    @endif
</div>

@if($blog->thumbnail_path)
<h3>📸 Thumbnail Preview</h3>
<img src="{{ $thumbnailUrl }}" alt="{{ $blog->title }}" class="thumbnail">
@endif

<div style="text-align: center; margin: 30px 0;">
    <a href="{{ $blogUrl }}" class="btn">View Blog Post</a>
</div>

@if(!empty($keywords))
<h3>🔑 Keywords</h3>
<p>{{ implode(', ', $keywords) }}</p>
@endif

<div class="alert-info">
    <strong>Next Steps:</strong> The blog is now live and indexed. Monitor analytics for performance.
</div>
@endsection
