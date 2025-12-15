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
        // Check for entity pattern (e.g., &rsquo;, &#039;)
        // Only decode if an entity is likely present to avoid unnecessary processing
        if (preg_match('/&([a-zA-Z0-9]+|#[0-9]{1,6}|#x[0-9a-fA-F]{1,6});/', $title)) {
            // ENT_HTML5 handles HTML5 entities
            // ENT_QUOTES decodes both double and single quotes
            return html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $title;
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
