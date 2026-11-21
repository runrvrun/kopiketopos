<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One product line of a transaction (table kept from v1: product_transaction). */
#[Fillable(['transaction_id', 'product_id', 'amount', 'price'])]
class TransactionLine extends Model
{
    protected $table = 'product_transaction';

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'price' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** Includes deleted products so past orders keep their product names. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function subtotal(): int
    {
        return $this->amount * $this->price;
    }
}
