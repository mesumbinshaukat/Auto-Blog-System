<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Blog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'custom_prompt',
        'meta_title',
        'meta_description',
        'tags_json',
        'table_of_contents_json',
        'category_id',
        'published_at',
        'views',
        'thumbnail_path',
    ];

    protected $casts = [
        'tags_json' => 'array',
        'table_of_contents_json' => 'array',
        'published_at' => 'datetime',
        'views' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getTableOfContentsAttribute(): array
    {
        // Return stored TOC if available
        if (!empty($this->attributes['table_of_contents_json'])) {
            return json_decode($this->attributes['table_of_contents_json'], true) ?? [];
        }

        // Fallback: Parse content
        return $this->generateTocFromContent($this->content);
    }

    public function generateTocFromContent(?string $content): array
    {
        if (!$content) return [];

        preg_match_all('/<h([2-3])(?:[^>]*)id="([^"]*)"(?:[^>]*)>(.*?)<\/h\1>/', $content, $matches, PREG_SET_ORDER);
        
        // Fallback for headings without IDs (if any)
        if (empty($matches)) {
            preg_match_all('/<h([2-3])>(.*?)<\/h\1>/', $content, $matches, PREG_SET_ORDER);
        }

        $toc = [];
        foreach ($matches as $match) {
            $id = $match[2] ?? \Illuminate\Support\Str::slug(strip_tags($match[3] ?? $match[2]));
            $title = strip_tags($match[3] ?? $match[2]);
            
            $toc[] = [
                'level' => $match[1],
                'title' => $title,
                'id' => $id
            ];
        }
        return $toc;
    }

    // Mutator to automatically add IDs to headings for linking
    protected function setContentAttribute($value)
    {
        $this->attributes['content'] = preg_replace_callback('/<h([2-3])>(.*?)<\/h\1>/', function ($matches) {
            $slug = \Illuminate\Support\Str::slug(strip_tags($matches[2]));
            return "<h{$matches[1]} id=\"$slug\">{$matches[2]}</h{$matches[1]}>";
        }, $value);
    }

    public function getTitleAttribute($value)
    {
        // Handle full URLs
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $path = parse_url($value, PHP_URL_PATH) ?? $value;
            $title = basename($path);
            if (empty($title) || $title === parse_url($value, PHP_URL_HOST)) {
                $title = str_replace('www.', '', parse_url($value, PHP_URL_HOST));
            }
            $title = str_replace(['-', '_'], ' ', $title);
            $title = preg_replace('/\.(html|php|asp|aspx)$/i', '', $title);
            return ucwords(trim($title));
        }
        
        // Handle malformed URLs (missing protocol separators like "httpswww...")
        if (preg_match('/^https?[a-z0-9]+/i', $value)) {
            // Common section keywords to look for
            $sections = ['news', 'tech', 'technology', 'business', 'sports', 'entertainment', 
                        'politics', 'science', 'health', 'world', 'opinion', 'lifestyle',
                        'travel', 'food', 'culture', 'arts', 'education', 'finance'];
            
            $foundSection = null;
            foreach ($sections as $section) {
                // Look for the section keyword in the URL
                if (preg_match('/(?:com|net|org|co)' . $section . '/i', $value)) {
                    $foundSection = $section;
                    break;
                }
            }
            
            if ($foundSection) {
                return ucwords($foundSection);
            } else {
                // Fallback: try to extract domain name
                if (preg_match('/(?:https?)?(?:www)?([a-z0-9\-]+)(?:com|net|org|co)/i', $value, $domainMatches)) {
                    return ucwords($domainMatches[1]);
                } else {
                    // Last resort: use a generic title
                    return 'Article';
                }
            }
        }
        
        return $value;
    }

    /**
     * Get the thumbnail URL
     */
    public function getThumbnailUrlAttribute(): string
    {
        if ($this->thumbnail_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->thumbnail_path)) {
            return asset('storage/' . $this->thumbnail_path);
        }
        
        // Fallback to category default
        $categorySlug = strtolower($this->category->slug ?? 'technology');
        
        // Check if category specific default exists
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists("thumbnails/{$categorySlug}-default.svg")) {
            return asset("storage/thumbnails/{$categorySlug}-default.svg");
        }
        
        return asset("storage/thumbnails/technology-default.svg");
    }
}
