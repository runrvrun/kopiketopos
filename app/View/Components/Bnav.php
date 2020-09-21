<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Bnav extends Component
{
    public $current;

    public function __construct($current = null)
    {
        $this->current = $current;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function render()
    {
        return view('components.bnav');
    }
}
