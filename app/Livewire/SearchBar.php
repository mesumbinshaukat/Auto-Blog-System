<?php

namespace App\Livewire;

use Livewire\Component;

class SearchBar extends Component
{
    public $query = '';
    public $results = [];

    public function updatedQuery()
    {
        if (strlen($this->query) >= 2) {
            $this->results = \App\Models\Blog::where('title', 'like', '%' . $this->query . '%')
                ->orWhere('content', 'like', '%' . $this->query . '%')
                ->take(5)
                ->get(['id', 'title', 'slug', 'thumbnail_path', 'published_at']);
        } else {
            $this->results = [];
        }
    }

    public function search()
    {
        if (strlen($this->query) >= 2) {
            return redirect()->route('search.index', ['q' => $this->query]);
        }
    }

    public function render()
    {
        return view('livewire.search-bar');
    }
}
