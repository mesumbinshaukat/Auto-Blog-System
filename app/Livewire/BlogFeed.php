<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Blog;

class BlogFeed extends Component
{
    public $perPage = 6;
    public $excludeIds = [];
    public $categoryId = null;

    public function mount($excludeIds = [], $categoryId = null)
    {
        $this->excludeIds = $excludeIds;
        $this->categoryId = $categoryId;
    }

    public function loadMore()
    {
        $this->perPage += 6;
    }

    public function render()
    {
        $feed = Blog::query()
            ->when($this->categoryId, function($q) {
                return $q->where('category_id', $this->categoryId);
            })
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
