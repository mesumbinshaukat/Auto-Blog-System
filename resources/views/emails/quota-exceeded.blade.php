@extends('emails.layout')

@section('title', '🚨 API Quota Exceeded')

@section('content')
<div class="alert-danger">
    <strong>API Quota Exceeded!</strong> The {{ $api }} API has reached its quota limit.
</div>

<div class="details">
    <div class="details-row">
        <span class="details-label">🤖 API Service:</span>
        <span class="details-value">{{ $api }}</span>
    </div>
    <div class="details-row">
        <span class="details-label">⏰ Occurred At:</span>
        <span class="details-value">{{ $occurredAt }}</span>
    </div>
    <div class="details-row">
        <span class="details-label">🔄 Next Retry:</span>
        <span class="details-value">{{ $retryAt }}</span>
    </div>
</div>

<h3>📊 Quota Details</h3>
<div class="alert-warning">
    {{ $details }}
</div>

<h3>🔄 Fallback Status</h3>
<p>The system is automatically using fallback APIs to continue operations:</p>
<ul>
    <li><strong>HuggingFace:</strong> Primary → Fallback</li>
    <li><strong>Gemini:</strong> Primary → Fallback</li>
    <li><strong>OpenRouter:</strong> Free tier models</li>
</ul>

<div class="alert-info">
    <strong>Recommended Actions:</strong>
    <ul>
        <li>Check {{ $api }} dashboard for quota limits</li>
        <li>Consider upgrading to paid tier if frequent</li>
        <li>Monitor fallback API usage</li>
        <li>Review generation schedule to reduce load</li>
    </ul>
</div>
@endsection
