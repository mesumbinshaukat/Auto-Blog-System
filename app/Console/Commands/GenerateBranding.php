<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ThumbnailService;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class GenerateBranding extends Command
{
    protected $signature = 'branding:generate';
    protected $description = 'Generate logo and favicon using SVG generation';

    public function handle()
    {
        $this->info('Generating branding assets...');

        if (!file_exists(public_path('images'))) {
            mkdir(public_path('images'), 0755, true);
        }

        try {
            // 1. Generate Logo (Horizontal)
            $logoSvg = $this->generateLogoSvg();
            file_put_contents(public_path('images/logo.svg'), $logoSvg);
            
            // Also create a fallback PNG/WebP if possible, but sticking to SVG for now as it's safe
            // For favicon, modern browsers support SVG. 
            
            $this->info('✅ Logo generated: public/images/logo.svg');

            // 2. Generate Favicon (Square)
            $faviconSvg = $this->generateFaviconSvg();
            file_put_contents(public_path('favicon.svg'), $faviconSvg);
            
            // Copy favicon.svg to favicon.ico for basic compatibility (browsers often handle renamed SVGs or ignore the extension mismatch if MIME is detected, but best to offer SVG link)
            // Actually, best practice is to link type="image/svg+xml"
            
            $this->info('✅ Favicon generated: public/favicon.svg');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Branding generation failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function generateLogoSvg(): string
    {
        return <<<SVG
<svg width="400" height="100" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="logoGrad" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" style="stop-color:#3B82F6;stop-opacity:1" />
      <stop offset="100%" style="stop-color:#1E40AF;stop-opacity:1" />
    </linearGradient>
  </defs>
  <!-- Icon Part -->
  <circle cx="50" cy="50" r="35" fill="url(#logoGrad)"/>
  <path d="M 35 50 L 50 35 L 65 50 L 50 65 Z" fill="white" />
  
  <!-- Text Part -->
  <text x="100" y="65" font-family="Arial, sans-serif" font-weight="bold" font-size="40" fill="#1F2937">AutoBlog</text>
</svg>
SVG;
    }

    protected function generateFaviconSvg(): string
    {
        return <<<SVG
<svg width="512" height="512" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="favGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#3B82F6;stop-opacity:1" />
      <stop offset="100%" style="stop-color:#1E40AF;stop-opacity:1" />
    </linearGradient>
  </defs>
  <rect width="512" height="512" rx="100" fill="url(#favGrad)"/>
  <path d="M 150 256 L 256 150 L 362 256 L 256 362 Z" fill="white" />
</svg>
SVG;
    }
}
