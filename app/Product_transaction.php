<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product_transaction extends Model
{
    protected $table = 'product_transaction';
    protected $guarded = ['id'];

    public function product()
    {
        return $this->belongsTo('App\Product');
    }
}
