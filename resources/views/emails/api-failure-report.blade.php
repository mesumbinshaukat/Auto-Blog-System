@extends('emails.layout')

@section('title', '🔴 Multiple API Failures')

@section('content')
<div class="alert-danger">
    <strong>Multiple API Failures Detected!</strong> All APIs in the fallback chain have failed.
</div>

<div class="details">
    <div class="details-row">
        <span class="details-label">⏰ Occurred At:</span>
        <span class="details-value">{{ $occurredAt }}</span>
    </div>
    <div class="details-row">
        <span class="details-label">🔄 Fallback Used:</span>
        <span class="details-value">{{ $fallback }}</span>
    </div>
</div>

<h3>🤖 Failed APIs</h3>
<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
    <thead>
        <tr style="background: #f8f9fa;">
            <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">API Service</th>
            <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Status</th>
            <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Error</th>
        </tr>
    </thead>
    <tbody>
        @foreach($apis as $api)
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #dee2e6;"><strong>{{ $api['name'] }}</strong></td>
            <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                <span class="badge badge-danger">Failed</span>
            </td>
            <td style="padding: 10px; border-bottom: 1px solid #dee2e6; font-size: 12px;">{{ $api['error'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="alert-warning">
    <strong>System Status:</strong> Using {{ $fallback }} to maintain operations.
</div>

<div class="alert-danger">
    <strong>🚨 URGENT ACTION REQUIRED</strong>
    <ul>
        <li><strong>Check all API keys immediately</strong></li>
        <li>Verify API quotas and limits</li>
        <li>Check internet connectivity</li>
        <li>Review API service status pages:
            <ul>
                <li>HuggingFace: <a href="https://status.huggingface.co">status.huggingface.co</a></li>
                <li>Google AI: <a href="https://status.cloud.google.com">status.cloud.google.com</a></li>
                <li>OpenRouter: <a href="https://openrouter.ai/status">openrouter.ai/status</a></li>
            </ul>
        </li>
        <li>Consider temporary pause of automated generation</li>
    </ul>
</div>
@endsection
