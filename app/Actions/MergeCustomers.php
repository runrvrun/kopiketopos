<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Merge a duplicate customer into another: its transactions (including
 * soft-deleted ones) move over, a missing address is taken from the duplicate,
 * and the duplicate is removed. Receipts keep the name typed on each order.
 */
class MergeCustomers
{
    public function handle(Customer $keep, Customer $duplicate): int
    {
        abort_if($keep->is($duplicate), 422, 'Tidak bisa menggabungkan pelanggan dengan dirinya sendiri.');

        return DB::transaction(function () use ($keep, $duplicate) {
            $moved = Transaction::withTrashed()->where('customer_id', $duplicate->id)->update(['customer_id' => $keep->id]);

            foreach (['address', 'phone'] as $field) {
                if (blank($keep->{$field}) && filled($duplicate->{$field})) {
                    $keep->{$field} = $duplicate->{$field};
                }
            }
            $keep->touch();
            $keep->save();
            $duplicate->delete(); // soft delete

            return $moved;
        });
    }
}
