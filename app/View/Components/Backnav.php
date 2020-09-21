<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Backnav extends Component
{
    public $title;
    public $button;
    public $backlink;

    public function __construct($title = null, $button = null, $backlink = null)
    {
        $this->title = $title;
        $this->button = $button;
        $this->backlink = $backlink;
    }

    public function render()
    {
        return view('components.backnav');
    }
}
