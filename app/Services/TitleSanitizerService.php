<?php

namespace App\Services;

use App\Models\Blog;
use Illuminate\Support\Str;

class TitleSanitizerService
{
    /**
     * Sanitize a title by decoding HTML entities if any are found.
     *
     * @param string $title
     * @return string
     */
    public function sanitizeTitle(string $title): string
    {
        // 1. Handle raw URLs
        if (filter_var($title, FILTER_VALIDATE_URL)) {
            $path = parse_url($title, PHP_URL_PATH) ?? $title;
            $clean = basename($path);
            if (empty($clean) || $clean === parse_url($title, PHP_URL_HOST)) {
                $clean = str_replace('www.', '', parse_url($title, PHP_URL_HOST));
            }
            $clean = str_replace(['-', '_'], ' ', $clean);
            $clean = preg_replace('/\.(html|php|asp|aspx)$/i', '', $clean);
            $title = ucwords(trim($clean));
        }

        // 2. Check for entity pattern (e.g., &rsquo;, &#039;)
        if (preg_match('/&([a-zA-Z0-9]+|#[0-9]{1,6}|#x[0-9a-fA-F]{1,6});/', $title)) {
            $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // 3. Cleanup spacing
        $title = preg_replace('/\s+/', ' ', $title);

        return trim($title);
    }

    /**
     * Check if a blog has a malformed title, fix it, and regenerate slug.
     *
     * @param Blog $blog
     * @return Blog
     */
    public function fixBlog(Blog $blog): Blog
    {
        $originalTitle = $blog->title;
        $cleanTitle = $this->sanitizeTitle($originalTitle);

        if ($cleanTitle !== $originalTitle) {
            $blog->title = $cleanTitle;
            
            // Regenerate slug to be safe and clean
            // Appending a timestamp to ensure uniqueness if the new slug collides
            $blog->slug = Str::slug($cleanTitle) . '-' . time();
            
            // Update meta title as well
            $blog->meta_title = Str::limit($cleanTitle, 60);

            $blog->save();
        }

        return $blog;
    }
}
