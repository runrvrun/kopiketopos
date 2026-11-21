<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['store_id', 'user_id', 'customer_id', 'key', 'customer', 'total_item', 'total_price', 'discount', 'downpayment', 'status'])]
class Transaction extends Model
{
    use SoftDeletes;

    public const STATUS_PAID = 'Paid';

    protected function casts(): array
    {
        return [
            'key' => 'integer',
            'total_item' => 'integer',
            'total_price' => 'integer',
            'discount' => 'integer',
            'downpayment' => 'integer',
        ];
    }

    /** Includes deleted stores so old receipts still show the store. */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function customerRecord(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }

    /** @param  array<int>  $storeIds */
    public function scopeForStores(Builder $query, array $storeIds): void
    {
        $query->whereIn('store_id', $storeIds);
    }

    public function scopePaid(Builder $query): void
    {
        $query->where('status', self::STATUS_PAID);
    }

    public function scopeOngoing(Builder $query): void
    {
        $query->whereNull('status');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /** Total after discount: what the sale is worth (v1 dashboard figure). */
    public function netTotal(): int
    {
        return max(0, $this->total_price - (int) $this->discount);
    }

    /** What the customer still pays: total − discount − down payment. */
    public function amountDue(): int
    {
        return max(0, $this->netTotal() - (int) $this->downpayment);
    }

    public function invoiceNumber(): string
    {
        return $this->key ? 'INV-'.$this->key : '#'.$this->id;
    }
}
