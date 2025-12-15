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

    /**
     * Get the thumbnail URL
     */
    public function getThumbnailUrlAttribute(): string
    {
        if ($this->thumbnail_path && \Storage::disk('public')->exists($this->thumbnail_path)) {
            return asset('storage/' . $this->thumbnail_path);
        }
        
        // Fallback to category default
        $categorySlug = strtolower($this->category->slug ?? 'technology');
        return asset("storage/thumbnails/{$categorySlug}-default.svg");
    }
}
