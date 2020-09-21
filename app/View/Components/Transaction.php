<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Transaction extends Component
{
    public $id;
    public $button;
    public $count;
    public $subtotal;
    public $status;
    public $date;

    public function __construct($id = null, $button = null, $count = null, $subtotal = null, $status = null, $date = null)
    {
        $this->id = $id;
        $this->button = $button;
        $this->count = $count;
        $this->subtotal = $subtotal;
        $this->status = $status;
        $this->date = $date;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function render()
    {
        return view('components.transaction');
    }
}
