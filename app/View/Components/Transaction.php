<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Transaction extends Component
{
    public $id;
    public $button;
    public $count;
    public $subtotal;
    public $discount;
    public $total;
    public $status;
    public $date;
    public $store;

    public function __construct($id = null, $button = null, $count = null, $subtotal = null, $discount = null, $status = null, $date = null, $store = null)
    {
        $this->id = $id;
        $this->button = $button;
        $this->count = $count;
        $this->subtotal = $subtotal;
        $this->discount = $discount;
        $this->total = $subtotal-$discount;
        $this->status = $status;
        $this->date = $date;
        $this->store = $store;
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
