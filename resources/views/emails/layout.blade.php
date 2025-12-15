<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f4f7fa; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .content { padding: 30px 20px; color: #333333; line-height: 1.6; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; border-top: 1px solid #dee2e6; }
        .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: #ffffff !important; text-decoration: none; border-radius: 6px; margin: 15px 0; font-weight: 500; }
        .btn:hover { background: #5568d3; }
        .alert-success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; border-radius: 4px; color: #155724; }
        .alert-danger { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 15px 0; border-radius: 4px; color: #721c24; }
        .alert-warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 4px; color: #856404; }
        .alert-info { background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px; margin: 15px 0; border-radius: 4px; color: #0c5460; }
        .details { background: #f8f9fa; padding: 20px; border-radius: 6px; margin: 20px 0; }
        .details-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #dee2e6; }
        .details-row:last-child { border-bottom: none; }
        .details-label { font-weight: 600; color: #495057; }
        .details-value { color: #6c757d; text-align: right; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; border: 1px solid #dee2e6; color: #212529; }
        img.thumbnail { max-width: 100%; height: auto; border-radius: 6px; margin: 15px 0; border: 1px solid #dee2e6; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .badge-success { background: #28a745; color: #ffffff; }
        .badge-danger { background: #dc3545; color: #ffffff; }
        .badge-warning { background: #ffc107; color: #212529; }
        h2 { color: #212529; font-size: 20px; margin-top: 0; }
        h3 { color: #495057; font-size: 16px; margin-top: 20px; }
        ul { padding-left: 20px; }
        li { margin: 8px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>@yield('title', 'Auto Blog System')</h1>
        </div>
        <div class="content">
            @yield('content')
        </div>
        <div class="footer">
            <p><strong>Auto Blog System</strong> | {{ config('app.name') }}</p>
            <p>Generated at {{ now()->format('M d, Y H:i:s') }}</p>
            <p style="margin-top: 10px; color: #adb5bd;">This is an automated notification. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
