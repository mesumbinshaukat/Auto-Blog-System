# System Error Alert: {{ $errorType }}

The following error was detected in the Auto-Blog system:

**Error Type:** {{ $errorType }}
**Message:** {{ $errorMessage }}

**Timestamp:** {{ now()->toDateTimeString() }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
