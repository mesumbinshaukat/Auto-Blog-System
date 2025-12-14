<?php
/**
 * Image Serving Diagnostic Script
 * Place this in: /home/u146506433/domains/worldoftech.company/public_html/blogs/public/
 * Access via: https://blogs.worldoftech.company/test_images.php
 */

echo "<!DOCTYPE html><html><head><title>Image Diagnostic</title></head><body>";
echo "<h1>Image Serving Diagnostic</h1>";

// Check storage link
echo "<h2>Storage Link Check</h2>";
$storageLink = __DIR__ . '/storage';
if (is_link($storageLink)) {
    echo "✓ Storage symlink exists<br>";
    $target = readlink($storageLink);
    echo "Target: $target<br>";
    if (file_exists($target)) {
        echo "✓ Target directory exists<br>";
    } else {
        echo "✗ Target directory does NOT exist<br>";
    }
} else {
    echo "✗ Storage symlink does NOT exist<br>";
    echo "Expected location: $storageLink<br>";
}

// Check thumbnails directory
echo "<h2>Thumbnails Directory Check</h2>";
$thumbnailsDir = dirname(__DIR__) . '/storage/app/public/thumbnails';
echo "Checking: $thumbnailsDir<br>";
if (is_dir($thumbnailsDir)) {
    echo "✓ Thumbnails directory exists<br>";
    $files = scandir($thumbnailsDir);
    $imageFiles = array_filter($files, function($f) {
        return !in_array($f, ['.', '..']);
    });
    echo "Files found: " . count($imageFiles) . "<br>";
    
    if (count($imageFiles) > 0) {
        echo "<h3>Sample Images:</h3>";
        $sample = array_slice($imageFiles, 0, 5);
        foreach ($sample as $file) {
            $fullPath = $thumbnailsDir . '/' . $file;
            $webPath = '/storage/thumbnails/' . $file;
            $size = filesize($fullPath);
            $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
            
            echo "<div style='border:1px solid #ccc; padding:10px; margin:10px 0;'>";
            echo "<strong>$file</strong><br>";
            echo "Size: " . number_format($size) . " bytes<br>";
            echo "Permissions: $perms<br>";
            echo "Web Path: $webPath<br>";
            echo "<img src='$webPath' style='max-width:200px; border:1px solid #000;' onerror='this.style.border=\"3px solid red\"; this.alt=\"FAILED TO LOAD\"'><br>";
            echo "</div>";
        }
    }
} else {
    echo "✗ Thumbnails directory does NOT exist<br>";
}

// Check .htaccess
echo "<h2>.htaccess Check</h2>";
$htaccess = __DIR__ . '/.htaccess';
if (file_exists($htaccess)) {
    echo "✓ .htaccess exists<br>";
    echo "<pre>" . htmlspecialchars(file_get_contents($htaccess)) . "</pre>";
} else {
    echo "✗ .htaccess does NOT exist<br>";
}

// Check mod_rewrite
echo "<h2>Apache Modules</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo in_array('mod_rewrite', $modules) ? "✓ mod_rewrite enabled<br>" : "✗ mod_rewrite NOT enabled<br>";
} else {
    echo "Cannot check modules (not running under Apache or function disabled)<br>";
}

// PHP Info
echo "<h2>PHP Configuration</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "<br>";

echo "</body></html>";
