<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Blog;

class BlogFeed extends Component
{
    public $perPage = 6;
    public $excludeIds = [];

    public function mount($excludeIds = [])
    {
        $this->excludeIds = $excludeIds; // Exclude IDs already shown in Hero/Static sections
    }

    public function loadMore()
    {
        $this->perPage += 6;
    }

    public function render()
    {
        $feed = Blog::query()
            ->when(!empty($this->excludeIds), function($q) {
                return $q->whereNotIn('id', $this->excludeIds);
            })
            ->latest('published_at')
            ->take($this->perPage)
            ->get();

        $total = Blog::count();

        return view('livewire.blog-feed', [
            'feed' => $feed,
            'hasMore' => $feed->count() < ($total - count($this->excludeIds))
        ]);
    }
}
