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
        'meta_title',
        'meta_description',
        'tags_json',
        'category_id',
        'published_at',
        'views',
    ];

    protected $casts = [
        'tags_json' => 'array',
        'published_at' => 'datetime',
        'views' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getTableOfContentsAttribute(): array
    {
        preg_match_all('/<h([2-3])>(.*?)<\/h\1>/', $this->content, $matches, PREG_SET_ORDER);
        
        $toc = [];
        foreach ($matches as $match) {
            $toc[] = [
                'level' => $match[1],
                'title' => strip_tags($match[2]),
                'id' => \Illuminate\Support\Str::slug(strip_tags($match[2]))
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
}
