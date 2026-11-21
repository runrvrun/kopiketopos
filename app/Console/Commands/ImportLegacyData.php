<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Copies v1 production data (loaded raw into the `legacy` connection) into the
 * v2 schema. Run on an empty, freshly migrated database:
 *
 *   php artisan migrate:fresh && php artisan legacy:import
 *
 * Products, transactions and their lines must survive exactly; the command
 * finishes by comparing counts and totals between both databases.
 */
#[Signature('legacy:import {--force : Skip the confirmation prompt}')]
#[Description('Import stores, users, products and transactions from the v1 database')]
class ImportLegacyData extends Command
{
    /** v1 printed this address on every receipt; it belongs to the Pamulang store. */
    private const PAMULANG_ADDRESS = 'Jl. Alam Asri 1 Blok H 35 No. 5, Pamulang, Tangerang Selatan';

    private const PAMULANG_PHONE = '081213327373';

    public function handle(): int
    {
        $legacy = DB::connection('legacy');
        $target = DB::connection();

        foreach (['stores', 'users', 'customers', 'product_categories', 'products', 'category_product', 'transactions', 'product_transaction'] as $table) {
            if ($target->table($table)->exists()) {
                $this->error("Table `{$table}` is not empty. Run `php artisan migrate:fresh` first.");

                return self::FAILURE;
            }
        }

        if (! $this->option('force') && ! $this->confirm('Import v1 data from `'.$legacy->getDatabaseName().'`?', true)) {
            return self::FAILURE;
        }

        $target->transaction(function () use ($legacy, $target) {
            $this->importStores($legacy, $target);
            $this->importUsers($legacy, $target);
            $this->importProducts($legacy, $target);
            $customerIds = $this->importCustomers($legacy, $target);
            $this->importTransactions($legacy, $target, $customerIds);
            $this->importLines($legacy, $target);
        });

        return $this->verify($legacy, $target) ? self::SUCCESS : self::FAILURE;
    }

    private function importStores(Connection $legacy, Connection $target): void
    {
        $rows = $legacy->table('stores')->orderBy('id')->get()->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name ?? 'Toko '.$s->id,
            'address' => $s->id === 1 ? self::PAMULANG_ADDRESS : null,
            'phone' => $s->id === 1 ? self::PAMULANG_PHONE : null,
        ]);

        $target->table('stores')->insert($rows->all());
        $this->line("stores: {$rows->count()}");
    }

    private function importUsers(Connection $legacy, Connection $target): void
    {
        $users = $legacy->table('users')->orderBy('id')->get();

        $target->table('users')->insert($users->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role === 'admin' ? 'admin' : 'user',
            'email_verified_at' => $u->email_verified_at,
            'password' => $u->password,
            'remember_token' => $u->remember_token,
            'created_at' => $u->created_at,
            'updated_at' => $u->updated_at,
        ])->all());

        // v1 had a single users.store_id; admins see every store by role.
        $access = $users
            ->filter(fn ($u) => $u->role !== 'admin' && $u->store_id > 0)
            ->map(fn ($u) => ['store_id' => $u->store_id, 'user_id' => $u->id]);

        $target->table('store_user')->insert($access->values()->all());
        $this->line("users: {$users->count()} ({$access->count()} store assignments)");
    }

    /** v1 offered these categories in its product form, in this order. */
    private const V1_CATEGORIES = [
        'Coffee-Based Drinks',
        'Non-Coffee Drinks',
        'Boba Series',
        'Frappucino Series',
        'Instant Series',
        'Snacks',
    ];

    private function importProducts(Connection $legacy, Connection $target): void
    {
        $products = $legacy->table('products')->orderBy('id')->get();

        // v1 stored the category as free text: turn every distinct value into a row,
        // keeping v1's form order first, then any other values found in the data.
        $names = collect(self::V1_CATEGORIES)
            ->merge($products->pluck('category')->filter()->unique()->diff(self::V1_CATEGORIES)->sort())
            ->values();
        $target->table('product_categories')->insert($names->map(fn ($name, $i) => [
            'name' => $name,
            'sort_order' => $i + 1,
        ])->all());
        $categoryIds = $target->table('product_categories')->pluck('id', 'name');
        $this->line("categories: {$names->count()} ({$names->implode(', ')})");

        $rows = $products->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'price' => $p->price,
            'image' => $p->image,
            'is_active' => true,
            'created_at' => $p->created_at,
            'updated_at' => $p->updated_at,
        ]);

        $target->table('products')->insert($rows->all());

        // v1 allowed one category per product; v2 links products to categories many-to-many.
        $links = $products->filter(fn ($p) => $p->category)
            ->map(fn ($p) => ['category_id' => $categoryIds[$p->category], 'product_id' => $p->id]);
        $target->table('category_product')->insert($links->values()->all());

        $this->line("products: {$rows->count()} ({$links->count()} with a category)");
    }

    /**
     * v1 only stored a free-text customer per transaction, typed as
     * "<name>, <address/area, city>". Each distinct string (ignoring case/extra
     * spaces) becomes one customer, split into name and address
     * (Customer::splitLegacy), using its most recent spelling. Transactions keep
     * the original string as their receipt name.
     *
     * @return array<string, int> key of the full v1 string => customer id
     */
    private function importCustomers(Connection $legacy, Connection $target): array
    {
        $byKey = [];
        foreach ($legacy->table('transactions')->whereNotNull('customer')->orderBy('created_at')->get(['customer', 'created_at']) as $t) {
            $key = Customer::keyFor($t->customer);
            if ($key === '') {
                continue;
            }
            $byKey[$key] ??= ['first' => $t->created_at];
            $byKey[$key]['name'] = mb_substr(preg_replace('/\s+/', ' ', trim($t->customer)), 0, 100);
            $byKey[$key]['last'] = $t->created_at;
        }

        $ids = [];
        $id = 0;
        foreach (array_chunk($byKey, 1000, true) as $chunk) {
            $rows = [];
            foreach ($chunk as $key => $c) {
                $ids[$key] = ++$id;
                [$name, $address] = Customer::splitLegacy($c['name']);
                $rows[] = [
                    'id' => $id, 'name' => mb_substr($name, 0, 100), 'name_key' => Customer::keyFor($name),
                    'address' => $address ? mb_substr($address, 0, 255) : null, 'phone' => null,
                    'created_at' => $c['first'], 'updated_at' => $c['last'],
                ];
            }
            $target->table('customers')->insert($rows);
        }

        $this->line('customers: '.count($ids).' (from v1 customer names, split into name + address)');

        return $ids;
    }

    /** @param  array<string, int>  $customerIds */
    private function importTransactions(Connection $legacy, Connection $target, array $customerIds): void
    {
        $count = 0;

        $legacy->table('transactions')->orderBy('id')->chunk(1000, function ($chunk) use ($target, $customerIds, &$count) {
            $target->table('transactions')->insert($chunk->map(fn ($t) => [
                'id' => $t->id,
                'store_id' => $t->store_id,
                'customer_id' => $customerIds[Customer::keyFor($t->customer)] ?? null,
                'user_id' => $t->user_id,
                'key' => $t->key,
                'customer' => $t->customer,
                'total_item' => $t->total_item,
                'total_price' => $t->total_price,
                'discount' => $t->discount,
                'downpayment' => $t->downpayment,
                'status' => $t->status,
                'created_at' => $t->created_at,
                'updated_at' => $t->updated_at,
            ])->all());
            $count += $chunk->count();
        });

        $this->line("transactions: {$count}");
    }

    private function importLines(Connection $legacy, Connection $target): void
    {
        $count = 0;

        $legacy->table('product_transaction as pt')
            ->join('products as p', 'p.id', '=', 'pt.product_id')
            ->select('pt.*', 'p.price')
            ->orderBy('pt.id')
            ->chunk(2000, function ($chunk) use ($target, &$count) {
                $target->table('product_transaction')->insert($chunk->map(fn ($l) => [
                    'id' => $l->id,
                    'transaction_id' => $l->transaction_id,
                    'product_id' => $l->product_id,
                    'amount' => $l->amount,
                    'price' => $l->price,
                    'created_at' => $l->created_at,
                    'updated_at' => $l->updated_at,
                ])->all());
                $count += $chunk->count();
            });

        $this->line("order lines: {$count}");
    }

    private function verify(Connection $legacy, Connection $target): bool
    {
        $checks = [
            'products' => fn (Connection $db) => $db->table('products')->selectRaw('COUNT(*) c, SUM(price) s')->first(),
            'transactions' => fn (Connection $db) => $db->table('transactions')
                ->selectRaw('COUNT(*) c, SUM(total_price) s, SUM(COALESCE(discount,0)) d, SUM(UNIX_TIMESTAMP(created_at)) t')->first(),
            'order lines' => fn (Connection $db) => $db->table('product_transaction')
                ->selectRaw('COUNT(*) c, SUM(amount) s, SUM(product_id) p, SUM(transaction_id) t')->first(),
        ];

        $ok = true;
        foreach ($checks as $label => $query) {
            $a = (array) $query($legacy);
            $b = (array) $query($target);
            $match = $a == $b;
            $ok = $ok && $match;
            $this->line(sprintf('%s %-13s %s', $match ? '<info>✓</info>' : '<error>✗</error>', $label, json_encode($b)));
        }

        $v1 = $legacy->table('products')->pluck('category', 'id')->map(fn ($c) => $c ?: null)->all();
        $v2 = $target->table('products')
            ->leftJoin('category_product as cp', 'cp.product_id', '=', 'products.id')
            ->leftJoin('product_categories as c', 'c.id', '=', 'cp.category_id')
            ->pluck('c.name', 'products.id')->all();
        $categoriesMatch = $v1 == $v2;
        $ok = $ok && $categoriesMatch;
        $this->line(sprintf('%s %-13s %d products keep their v1 category', $categoriesMatch ? '<info>✓</info>' : '<error>✗</error>', 'categories', count($v2)));

        $named = $legacy->table('transactions')->whereNotNull('customer')->whereRaw("TRIM(customer) <> ''")->count();
        $linked = $target->table('transactions')->whereNotNull('customer_id')->count();
        $customersMatch = $named === $linked;
        $ok = $ok && $customersMatch;
        $this->line(sprintf('%s %-13s %d named transactions linked to a customer', $customersMatch ? '<info>✓</info>' : '<error>✗</error>', 'customers', $linked));

        $ok ? $this->info('Import verified: data matches v1.') : $this->error('Mismatch between v1 and v2 data!');

        return $ok;
    }
}
