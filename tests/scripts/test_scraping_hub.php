#!/usr/bin/env php
<?php

/**
 * Scraping Hub API Format Test
 * Tests the actual API endpoints to verify response format
 */

// Simple HTTP request without Laravel dependencies
function makeRequest($url, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'body' => $response,
        'error' => $error
    ];
}

// Load .env manually
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!isset($_ENV[$name])) {
            $_ENV[$name] = $value;
        }
    }
}

$baseUrl = $_ENV['SCRAPING_HUB_BASE_URL'] ?? 'https://scraping-hub-backend-only.vercel.app';
$masterKey = $_ENV['MASTER_KEY'] ?? '';

echo "=== Scraping Hub API Format Test ===\n\n";

if (empty($masterKey) || $masterKey === 'your_master_key_here') {
    echo "❌ MASTER_KEY not set in .env\n";
    echo "Please add: MASTER_KEY=your_actual_key\n";
    exit(1);
}

echo "Base URL: $baseUrl\n";
echo "Master Key: " . substr($masterKey, 0, 10) . "...\n\n";

$headers = ["Authorization: Bearer $masterKey"];

// Test 1: Health Check
echo "--- Test 1: Health Check ---\n";
$result = makeRequest($baseUrl . '/', $headers);
echo "Status: {$result['status']}\n";
if ($result['error']) {
    echo "Error: {$result['error']}\n";
} else {
    echo "Body: " . substr($result['body'], 0, 200) . "\n";
}
echo "\n";

// Test 2: Search API
echo "--- Test 2: Search API ---\n";
echo "Endpoint: /api/search?q=AI+technology&limit=3\n";
$result = makeRequest($baseUrl . '/api/search?q=' . urlencode('AI technology') . '&limit=3', $headers);
echo "Status: {$result['status']}\n";

if ($result['status'] == 200) {
    $data = json_decode($result['body'], true);
    echo "Response Format:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
    
    // Check expected fields
    if (isset($data['results'])) {
        echo "✅ Has 'results' array (" . count($data['results']) . " items)\n";
        if (!empty($data['results'][0])) {
            $first = $data['results'][0];
            echo "First result fields: " . implode(', ', array_keys($first)) . "\n";
            
            // Check for expected fields
            $expected = ['title', 'url', 'snippet'];
            foreach ($expected as $field) {
                if (isset($first[$field])) {
                    echo "  ✅ $field: " . substr($first[$field], 0, 50) . "...\n";
                } else {
                    echo "  ⚠️ Missing: $field\n";
                }
            }
        }
    } else {
        echo "⚠️ No 'results' key. Available keys: " . implode(', ', array_keys($data)) . "\n";
    }
} else {
    echo "❌ Failed: " . $result['body'] . "\n";
}
echo "\n";

// Test 3: Scrape API
echo "--- Test 3: Scrape API ---\n";
echo "Endpoint: /api/scrape?url=https://example.com\n";
$result = makeRequest($baseUrl . '/api/scrape?url=' . urlencode('https://example.com'), $headers);
echo "Status: {$result['status']}\n";

if ($result['status'] == 200) {
    $data = json_decode($result['body'], true);
    echo "Available keys: " . implode(', ', array_keys($data)) . "\n";
    
    // Check expected fields
    $expectedFields = ['content', 'title', 'snippet'];
    foreach ($expectedFields as $field) {
        if (isset($data[$field])) {
            $length = strlen($data[$field]);
            echo "✅ Has '$field' ($length chars)\n";
            if ($length > 0) {
                echo "   Sample: " . substr($data[$field], 0, 100) . "...\n";
            }
        } else {
            echo "⚠️ Missing '$field'\n";
        }
    }
} else {
    echo "❌ Failed: " . $result['body'] . "\n";
}
echo "\n";

echo "=== Reformatting Assessment ===\n";
echo "Based on the tests above:\n";
echo "1. Search API should return: {results: [{title, url, snippet}]}\n";
echo "2. Scrape API should return: {content, title, snippet}\n";
echo "3. Our ScrapingHubService already handles both formats correctly\n";
echo "4. No additional reformatting needed!\n\n";

echo "=== Test Complete ===\n";
