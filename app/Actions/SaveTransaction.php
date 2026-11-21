<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a transaction. Totals are always recomputed here from
 * stored prices (v1 trusted totals sent by the browser).
 */
class SaveTransaction
{
    /**
     * @param  array<int|string, int|string>  $items  product_id => quantity
     */
    public function handle(
        User $user,
        ?Transaction $transaction,
        int $storeId,
        array $items,
        ?string $customer,
        int $discount,
        int $downpayment,
        bool $pay,
        ?string $customerAddress = null,
        ?int $customerId = null,
        ?string $customerPhone = null,
    ): Transaction {
        if (! $user->canAccessStore($storeId)) {
            throw ValidationException::withMessages(['storeId' => 'Pilih toko untuk transaksi ini.']);
        }

        $items = collect($items)
            ->mapWithKeys(fn ($qty, $id) => [(int) $id => min(999, max(0, (int) $qty))])
            ->filter(fn ($qty) => $qty > 0);

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Pesanan masih kosong. Tambahkan minimal satu produk.']);
        }

        // Lines already on the order keep the price they were sold at.
        $lockedPrices = $transaction?->lines()->pluck('price', 'product_id') ?? collect();
        // Deleted or inactive products may stay on an order they're already on,
        // but can't be added to new ones.
        $products = Product::withTrashed()->whereIn('id', $items->keys())->get()->keyBy('id');

        $lines = $items
            ->filter(fn ($qty, $id) => $products->has($id)
                && (($products[$id]->is_active && ! $products[$id]->trashed()) || $lockedPrices->has($id)))
            ->map(fn ($qty, $id) => [
                'product_id' => $id,
                'amount' => $qty,
                'price' => (int) ($lockedPrices[$id] ?? $products[$id]->price),
            ]);

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Produk tidak ditemukan.']);
        }

        $totalPrice = $lines->sum(fn ($l) => $l['amount'] * $l['price']);
        $discount = min(max(0, $discount), $totalPrice);
        $downpayment = min(max(0, $downpayment), $totalPrice - $discount);

        return DB::transaction(function () use ($user, $transaction, $storeId, $lines, $customer, $customerAddress, $customerId, $customerPhone, $totalPrice, $discount, $downpayment, $pay) {
            $customerRecord = $this->resolveCustomer($customer, $customerAddress, $customerPhone, $customerId);

            $attributes = [
                'store_id' => $storeId,
                'customer_id' => $customerRecord?->id,
                'customer' => $customerRecord?->name,
                'total_item' => $lines->sum('amount'),
                'total_price' => $totalPrice,
                'discount' => $discount ?: null,
                'downpayment' => $downpayment ?: null,
            ];

            if ($pay) {
                $attributes['status'] = Transaction::STATUS_PAID;
            }

            if ($transaction) {
                $transaction->update($attributes);
            } else {
                $transaction = Transaction::create($attributes + [
                    'user_id' => $user->id,
                    'key' => $this->nextKey(),
                ]);
            }

            $transaction->lines()->delete();
            $transaction->lines()->createMany($lines->values()->all());

            return $transaction;
        });
    }

    /**
     * Find or create the customer for this order:
     * 1. a picked autocomplete suggestion, if its name still matches;
     * 2. otherwise customers with the same (case/space-insensitive) name — several
     *    people can share a name, so a typed address picks the one living there
     *    (or one without an address yet); with no address, the most recent one;
     * 3. otherwise a new customer.
     * A filled-in address/phone updates the customer.
     */
    private function resolveCustomer(?string $name, ?string $address, ?string $phone, ?int $customerId): ?Customer
    {
        if (blank($name)) {
            return null;
        }

        $key = Customer::keyFor($name);
        $address = filled($address) ? mb_substr(trim($address), 0, 255) : null;
        $phone = filled($phone) ? mb_substr(preg_replace('/[^0-9+]/', '', $phone), 0, 30) : null;

        $customer = $customerId ? Customer::find($customerId) : null;

        if (! $customer || $customer->name_key !== $key) {
            $sameName = Customer::matching($name)->latest('updated_at')->get();

            $customer = match (true) {
                $address === null => $sameName->first(),
                default => $sameName->first(fn ($c) => Customer::keyFor($c->address) === Customer::keyFor($address))
                    ?? $sameName->first(fn ($c) => blank($c->address)),
            } ?? new Customer(['name' => $name]);
        }

        if ($address !== null) {
            $customer->address = $address;
        }
        if ($phone) {
            $customer->phone = $phone;
        }
        $customer->save();

        return $customer;
    }

    /** Invoice number: Ymd + 5-digit daily sequence, e.g. 2026112100007. */
    private function nextKey(): int
    {
        $base = (int) now()->format('Ymd') * 100000;

        $last = Transaction::withTrashed()   // deleted transactions keep their number
            ->where('key', '>', $base)
            ->where('key', '<', $base + 100000)
            ->lockForUpdate()
            ->max('key');

        return $last ? $last + 1 : $base + 1;
    }
}
