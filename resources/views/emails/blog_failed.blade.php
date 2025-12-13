<!DOCTYPE html>
<html>
<head>
    <title>Blog Generation Failed</title>
</head>
<body>
    <h1>Alert: Blog Generation Failed</h1>
    <p>The automated blog generation process encountered an error.</p>
    
    <p><strong>Category:</strong> {{ $categoryName }}</p>
    <p><strong>Error Message:</strong></p>
    <pre>{{ $errorMessage }}</pre>
    
    <p>Timestamp: {{ now() }}</p>
</body>
</html>
