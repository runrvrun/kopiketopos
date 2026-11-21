<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'price', 'image', 'is_active'])]
class Product extends Model
{
    use SoftDeletes;

    public const DEFAULT_IMAGE = 'uploads/product/default.png';

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** A product may belong to several categories. */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'category_product', 'product_id', 'category_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Public URL of the product photo, falling back to the placeholder. */
    public function imageUrl(): string
    {
        $path = $this->image && is_file(public_path($this->image)) ? $this->image : self::DEFAULT_IMAGE;

        return asset($path);
    }
}
