<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Producthalfside extends Component
{
    public $item;
    public $button;

    public function __construct($item = null, $button = null)
    {
        $this->item = $item;
        $this->button = $button;
    }


    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function render()
    {
        return view('components.producthalfside');
    }
}
