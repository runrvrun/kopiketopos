<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'address', 'phone'])]
class Customer extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            $customer->name = mb_substr(preg_replace('/\s+/', ' ', trim($customer->name)), 0, 100);
            $customer->name_key = self::keyFor($customer->name);
        });
    }

    /**
     * Split a v1 customer string, always typed as "<name>, <address/area, city>",
     * into [name, address]. Without a comma the whole string is the name.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function splitLegacy(string $value): array
    {
        $clean = fn (string $s) => trim(preg_replace('/\s+/', ' ', $s), " \t\n\r\0\x0B,");
        $parts = explode(',', $value, 2);
        $name = $clean($parts[0]);
        $address = isset($parts[1]) ? $clean($parts[1]) : '';

        // A leading comma (", Pamulang") leaves no name: keep the text as the name.
        if ($name === '') {
            return [$clean($value), null];
        }

        return [$name, $address !== '' ? $address : null];
    }

    /** Normalised name used for matching: "  Budi  Santoso" → "budi santoso". */
    public static function keyFor(?string $name): string
    {
        return mb_substr(mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $name))), 0, 100);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Adds visits (paid transactions), spend (net) and last_at (last paid
     * purchase) columns. Deleted transactions are excluded.
     */
    public function scopeWithStats(Builder $query): void
    {
        $paid = fn (string $select) => Transaction::query()->paid()->whereColumn('transactions.customer_id', 'customers.id')->selectRaw($select);

        $query->select('customers.*')->addSelect([
            'visits' => $paid('COUNT(*)'),
            'spend' => $paid('COALESCE(SUM(total_price - COALESCE(discount, 0)), 0)'),
            'last_at' => $paid('MAX(created_at)'),
        ]);
    }

    public function scopeMatching(Builder $query, string $name): void
    {
        $query->where('name_key', self::keyFor($name));
    }
}
