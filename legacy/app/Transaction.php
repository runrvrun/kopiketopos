<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $guarded = ['id'];

    public function store()
    {
        return $this->belongsTo('App\Store');
    }
}
