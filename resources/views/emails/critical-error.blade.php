@extends('emails.layout')

@section('title', '🚨 CRITICAL ERROR')

@section('content')
<div class="alert-danger">
    <strong>CRITICAL SYSTEM ERROR!</strong> An unexpected error occurred in the Auto Blog System.
</div>

<div class="details">
    <div class="details-row">
        <span class="details-label">❗ Error Type:</span>
        <span class="details-value">{{ $errorType }}</span>
    </div>
    <div class="details-row">
        <span class="details-label">⏰ Occurred At:</span>
        <span class="details-value">{{ $occurredAt }}</span>
    </div>
    @if(!empty($contextData['job_id']))
    <div class="details-row">
        <span class="details-label">🆔 Job ID:</span>
        <span class="details-value">{{ $contextData['job_id'] }}</span>
    </div>
    @endif
    @if(!empty($contextData['attempt']))
    <div class="details-row">
        <span class="details-label">🔄 Attempt:</span>
        <span class="details-value">{{ $contextData['attempt'] }} of {{ $contextData['max_attempts'] ?? 5 }}</span>
    </div>
    @endif
</div>

<h3>❗ Error Message</h3>
<div class="alert-warning">
    {{ $error }}
</div>

@if(!empty($contextData))
<h3>📋 Context Information</h3>
<pre>{{ json_encode($contextData, JSON_PRETTY_PRINT) }}</pre>
@endif

<h3>🔍 Stack Trace</h3>
<pre>{{ $stackTrace }}</pre>

<div class="alert-danger">
    <strong>⚠️ IMMEDIATE ACTION REQUIRED</strong>
    <ul>
        <li>Review the error details above</li>
        <li>Check application logs for more context</li>
        <li>Verify all services are running</li>
        <li>Check database connectivity</li>
        <li>Verify API keys are valid</li>
    </ul>
</div>
@endsection
