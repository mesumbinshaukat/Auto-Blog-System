<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Blog; // Added this line

class BlogView extends Model
{
    protected $fillable = [
        'blog_id',
        'ip_address',
        'user_agent',
        'referer',
        'country_code',
        'read_time_seconds' // Estimated read time or time on page
    ];

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }
}
