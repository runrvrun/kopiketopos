<?php

use App\Actions\SaveTransaction;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Support\StoreContext;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Transaksi')] class extends Component
{
    #[Locked]
    public ?int $transactionId = null;

    public function mount(?Transaction $transaction = null): void
    {
        if ($transaction?->exists) {
            abort_unless(auth()->user()->canAccessStore($transaction->store_id), 403);
            $this->transactionId = $transaction->id;
        }
    }

    /**
     * @param  array<int|string, int>  $cart  product_id => quantity
     */
    /**
     * Checkout: save the order (new ones stay pending until "Lunas" on the
     * receipt page) and open its receipt.
     *
     * @param  array<int|string, int>  $cart  product_id => quantity
     */
    public function save(array $cart, ?string $customer, $discount, $downpayment, $storeId, ?string $address = null, $customerId = null, ?string $phone = null): void
    {
        $transaction = $this->transactionId ? Transaction::findOrFail($this->transactionId) : null;

        $saved = app(SaveTransaction::class)->handle(
            user: auth()->user(),
            transaction: $transaction,
            storeId: (int) $storeId,
            items: $cart,
            customer: $customer,
            discount: (int) preg_replace('/\D/', '', (string) $discount),
            downpayment: (int) preg_replace('/\D/', '', (string) $downpayment),
            pay: false,
            customerAddress: $address,
            customerId: $customerId ? (int) $customerId : null,
            customerPhone: $phone,
        );

        if ($transaction) {
            session()->flash('status', 'Transaksi diperbarui');
        }
        $this->redirectRoute('transactions.show', $saved);
    }

    /**
     * Autocomplete: customers whose name (or phone, when digits are typed)
     * contains the typed text; names starting with it first, then most recent.
     *
     * @return array<int, array{id: int, name: string, address: ?string, phone: ?string}>
     */
    public function searchCustomers(string $term): array
    {
        // LIKE wildcards never appear in names; drop them instead of escaping.
        $term = str_replace(['%', '_'], '', Customer::keyFor($term));
        if (mb_strlen($term) < 2) {
            return [];
        }

        $digits = preg_replace('/\D/', '', $term);

        return Customer::query()
            ->where(fn ($q) => $q->where('name_key', 'like', '%'.$term.'%')
                ->when(strlen($digits) >= 4, fn ($q) => $q->orWhere('phone', 'like', '%'.$digits.'%')))
            ->orderByRaw('CASE WHEN name_key LIKE ? THEN 0 ELSE 1 END', [$term.'%']) // names starting with the term first
            ->latest('updated_at')
            ->limit(6)
            ->get(['id', 'name', 'address', 'phone'])
            ->toArray();
    }

    public function delete(): void
    {
        $transaction = Transaction::findOrFail($this->transactionId);
        abort_unless(auth()->user()->canAccessStore($transaction->store_id), 403);

        $transaction->delete();

        session()->flash('status', 'Transaksi dihapus');
        $this->redirectRoute('transactions.index');
    }

    public function with(): array
    {
        $user = auth()->user();
        $context = StoreContext::for($user);
        $transaction = $this->transactionId ? Transaction::with(['lines', 'customerRecord'])->findOrFail($this->transactionId) : null;

        $lines = $transaction?->lines->keyBy('product_id') ?? collect();

        // Active products, plus any inactive ones already on this order.
        $products = Product::withTrashed()
            ->with('categories:id,name')
            ->where(fn ($q) => $q->where(fn ($q) => $q->active()->whereNull('deleted_at'))->orWhereIn('id', $lines->keys()))
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (int) ($lines[$p->id]->price ?? $p->price),
                'categories' => $p->categories->pluck('name')->whenEmpty(fn ($c) => $c->push('Lainnya'))->values(),
                'image' => $p->imageUrl(),
            ]);

        // Chips in the order set on the Kategori admin page; only categories with products.
        $present = $products->pluck('categories')->flatten()->unique();
        $categories = ProductCategory::ordered()->pluck('name')
            ->intersect($present)
            ->when($present->contains('Lainnya'), fn ($c) => $c->push('Lainnya'))
            ->values();

        // Best sellers of the last 90 days in the selected store(s), for the "Terlaris" chip.
        $bestSellers = TransactionLine::query()
            ->join('transactions', 'transactions.id', '=', 'product_transaction.transaction_id')
            ->whereIn('transactions.store_id', $context->storeIds())
            ->whereNull('transactions.deleted_at')
            ->where('transactions.created_at', '>=', now()->subDays(90))
            ->whereIn('product_transaction.product_id', $products->pluck('id'))
            ->groupBy('product_transaction.product_id')
            ->orderByRaw('SUM(product_transaction.amount) DESC')
            ->limit(12)
            ->pluck('product_transaction.product_id');

        $stores = $user->orderableStores();
        if ($transaction && ! $stores->contains('id', $transaction->store_id) && $transaction->store) {
            $stores = $stores->push($transaction->store); // keep an inactive store on its own old order
        }
        $current = $context->currentId();
        $storeId = $transaction?->store_id
            ?? ($stores->contains('id', $current) ? $current : null)
            ?? ($stores->count() === 1 ? $stores->first()->id : null);

        return [
            'transaction' => $transaction,
            'products' => $products->values(),
            'categories' => $categories,
            'bestSellers' => $bestSellers->values(),
            'stores' => $stores,
            'initial' => [
                'cart' => (object) $lines->mapWithKeys(fn ($l) => [$l->product_id => $l->amount])->all(),
                'customer' => $transaction?->customer ?? '',
                'address' => $transaction?->customerRecord?->address ?? '',
                'phone' => $transaction?->customerRecord?->phone ?? '',
                'customerId' => $transaction?->customer_id,
                'discount' => $transaction?->discount ?: '',
                'downpayment' => $transaction?->downpayment ?: '',
                'storeId' => $storeId,
            ],
            'chooseStore' => $stores->count() > 1,
            'noActiveStore' => $stores->isEmpty(),
        ];
    }
};
?>

<div
    x-data="posCart(@js($products), @js($bestSellers), @js($initial))"
    class="lg:grid lg:grid-cols-[1fr_22rem] lg:items-start lg:gap-6"
>
    {{-- Products --}}
    <div class="pb-24 lg:pb-0">
        <div class="flex items-center gap-2">
            <a href="{{ route('transactions.index') }}" class="btn-icon -ml-2" aria-label="Kembali"><x-icon name="arrow-left" /></a>
            <div class="min-w-0">
                <h1 class="truncate font-display text-xl font-semibold text-espresso-900">
                    {{ $transaction ? 'Ubah transaksi' : 'Transaksi baru' }}
                </h1>
                @if ($transaction)
                    <p class="text-xs text-roast">{{ $transaction->invoiceNumber() }} · {{ $transaction->created_at->translatedFormat('j M Y, H:i') }}
                        @if ($transaction->isPaid()) · <span class="font-medium text-espresso">Lunas</span> @endif
                    </p>
                @endif
            </div>
        </div>

        {{-- Search + categories (sticky under the header) --}}
        <div class="sticky top-[calc(3.5rem+env(safe-area-inset-top))] z-20 -mx-4 mt-3 bg-white/95 px-4 pb-2 pt-2 backdrop-blur sm:-mx-6 sm:px-6">
            <label class="relative block">
                <span class="sr-only">Cari produk</span>
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-[18px] -translate-y-1/2 text-roast" />
                <input type="search" x-model="search" class="input pl-10" placeholder="Cari produk…" autocomplete="off">
            </label>
            <div class="no-scrollbar -mx-4 mt-2 flex gap-2 overflow-x-auto px-4 sm:-mx-6 sm:px-6">
                <template x-for="cat in ['Semua'{{ $bestSellers->isNotEmpty() ? ", 'Terlaris'" : '' }}, ...@js($categories)]" :key="cat">
                    <button type="button" x-on:click="category = cat; search = ''"
                        class="h-9 shrink-0 rounded-full px-3.5 text-sm font-medium transition"
                        :class="category === cat ? 'bg-espresso text-foam' : 'bg-foam text-espresso-800 ring-1 ring-latte hover:bg-white'"
                        x-text="cat"></button>
                </template>
            </div>
        </div>

        <div class="mt-2 grid grid-cols-2 gap-2.5 sm:grid-cols-3 xl:grid-cols-4">
            <template x-for="p in filtered" :key="p.id">
                <div class="relative">
                    <button type="button" x-on:click="add(p.id)"
                        class="flex w-full flex-col overflow-hidden rounded-2xl bg-foam text-left ring-1 transition active:scale-[0.97]"
                        :class="qty(p.id) ? 'ring-2 ring-caramel shadow-md shadow-caramel/20' : 'ring-latte/80 hover:ring-caramel-300'">
                        <img :src="p.image" :alt="p.name" loading="lazy" decoding="async" class="aspect-[4/3] w-full bg-cream object-cover">
                        <span class="flex flex-1 flex-col p-2.5">
                            <span class="line-clamp-2 min-h-[2.5rem] text-sm font-medium leading-5 text-espresso-900" x-text="p.name"></span>
                            <span class="mt-1 text-sm font-semibold text-caramel-700 tabular-nums" x-text="rp(p.price)"></span>
                        </span>
                    </button>
                    <template x-if="qty(p.id)">
                        <div>
                            <span class="pointer-events-none absolute right-2 top-2 flex h-7 min-w-7 items-center justify-center rounded-full bg-espresso px-2 text-sm font-bold text-foam shadow" x-text="qty(p.id)"></span>
                            <button type="button" x-on:click="dec(p.id)" class="absolute left-2 top-2 flex size-8 items-center justify-center rounded-full bg-foam/95 text-espresso shadow ring-1 ring-latte active:scale-90" aria-label="Kurangi">
                                <x-icon name="minus" class="size-4" />
                            </button>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <p x-show="filtered.length === 0" x-cloak class="py-10 text-center text-sm text-roast">Produk tidak ditemukan.</p>
    </div>

    {{-- Mobile summary bar --}}
    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-latte bg-foam/95 px-4 pb-[calc(0.75rem+env(safe-area-inset-bottom))] pt-3 backdrop-blur lg:hidden">
        <button type="button" x-on:click="sheet = true" :disabled="count === 0"
            class="flex h-14 w-full items-center gap-3 rounded-2xl bg-espresso px-4 text-left text-foam shadow-lg shadow-espresso/25 transition active:scale-[0.99] disabled:bg-latte disabled:text-roast disabled:shadow-none">
            <span class="relative">
                <x-icon name="bag" class="size-6" />
                <span x-show="count" x-cloak class="absolute -right-2 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-caramel px-1 text-[11px] font-bold text-espresso-950" x-text="count"></span>
            </span>
            <span class="flex-1 text-sm font-medium" x-text="count ? 'Lihat pesanan' : 'Ketuk produk untuk menambah'"></span>
            <span class="font-display text-lg font-semibold tabular-nums" x-show="count" x-text="rp(due)"></span>
        </button>
    </div>

    {{-- Cart: bottom sheet on phones, side panel on desktop --}}
    <div x-show="sheet" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-espresso-950/40 lg:hidden" x-on:click="sheet = false"></div>
    <aside
        class="fixed inset-x-0 bottom-0 z-50 flex max-h-[88dvh] flex-col rounded-t-3xl bg-foam shadow-2xl transition-transform duration-200 ease-out lg:sticky lg:top-[calc(4.75rem+env(safe-area-inset-top))] lg:z-auto lg:max-h-[calc(100dvh-6rem)] lg:translate-y-0! lg:rounded-3xl lg:shadow-none lg:ring-1 lg:ring-latte/80"
        :class="sheet ? 'translate-y-0' : 'translate-y-full'"
        x-on:keydown.escape.window="sheet = false"
        aria-label="Pesanan"
    >
        <div class="flex justify-center pt-2.5 lg:hidden" aria-hidden="true"><div class="h-1.5 w-10 rounded-full bg-latte"></div></div>
        <div class="flex items-center justify-between px-5 pb-3 pt-2 lg:pt-4">
            <h2 class="font-display text-lg font-semibold text-espresso-900">Pesanan <span class="text-sm font-normal text-roast" x-text="count ? '(' + count + ' item)' : ''"></span></h2>
            <button type="button" class="btn-icon -mr-2 lg:hidden" x-on:click="sheet = false" aria-label="Tutup"><x-icon name="x" /></button>
        </div>

        <div class="flex-1 space-y-4 overflow-y-auto overscroll-contain px-5 pb-4">
            @error('items') <p class="rounded-xl bg-cherry/10 px-3 py-2 text-sm text-cherry">{{ $message }}</p> @enderror
            @if ($noActiveStore)
                <p class="rounded-xl bg-cherry/10 px-3 py-2 text-sm text-cherry">Tidak ada toko aktif untuk akun ini, jadi transaksi baru tidak bisa dibuat. Hubungi admin.</p>
            @endif
            @if (! $chooseStore)
                @error('storeId') <p class="rounded-xl bg-cherry/10 px-3 py-2 text-sm text-cherry">{{ $message }}</p> @enderror
            @endif

            @if ($chooseStore)
                <div>
                    <label for="trx-store" class="label">Toko</label>
                    <select id="trx-store" x-model.number="storeId" class="input">
                        <option value="">Pilih toko…</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}{{ $store->is_active ? '' : ' (nonaktif)' }}</option>
                        @endforeach
                    </select>
                    @error('storeId') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="relative" x-on:click.outside="suggestions = []">
                <label for="trx-customer" class="label">Nama pelanggan <span class="font-normal text-roast">(opsional)</span></label>
                <div class="relative">
                    <input id="trx-customer" type="text" x-model="customer" maxlength="100" class="input pr-9" placeholder="Ketik untuk mencari pelanggan" autocomplete="off"
                        role="combobox" aria-autocomplete="list" :aria-expanded="suggestions.length > 0" aria-controls="customer-suggestions"
                        x-on:input.debounce.250ms="lookupCustomers()"
                        x-on:keydown.down.prevent="highlight = Math.min(highlight + 1, suggestions.length - 1)"
                        x-on:keydown.up.prevent="highlight = Math.max(highlight - 1, 0)"
                        x-on:keydown.enter="if (suggestions[highlight]) { $event.preventDefault(); pickCustomer(suggestions[highlight]) }"
                        x-on:keydown.escape.stop="suggestions = []">
                    <span x-show="customerId" x-cloak class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[#1f7a45]" title="Pelanggan terdaftar"><x-icon name="check" class="size-4" /></span>
                </div>
                <ul id="customer-suggestions" x-show="suggestions.length" x-cloak role="listbox"
                    class="absolute inset-x-0 top-full z-10 mt-1 max-h-60 overflow-y-auto rounded-xl bg-white py-1 shadow-xl shadow-espresso/10 ring-1 ring-latte">
                    <template x-for="(c, i) in suggestions" :key="c.id">
                        <li role="option" :aria-selected="i === highlight">
                            <button type="button" x-on:click="pickCustomer(c)" x-on:mouseenter="highlight = i"
                                class="block w-full px-3 py-2.5 text-left" :class="i === highlight ? 'bg-cream' : ''">
                                <span class="block text-sm font-medium text-espresso-900" x-text="c.name"></span>
                                <span class="block truncate text-xs text-roast" x-text="[c.address || 'Alamat belum diisi', c.phone].filter(Boolean).join(' · ')"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>

            <div>
                <label for="trx-address" class="label">Alamat <span class="font-normal text-roast">(opsional)</span></label>
                <textarea id="trx-address" x-model="address" rows="2" maxlength="255" class="input h-auto py-2.5" placeholder="Alamat pengiriman"></textarea>
            </div>

            <div>
                <label for="trx-phone" class="label">No. HP <span class="font-normal text-roast">(opsional)</span></label>
                <input id="trx-phone" type="tel" inputmode="tel" x-model="phone" maxlength="30" class="input" placeholder="08xx" autocomplete="off">
            </div>

            <ul class="divide-y divide-latte/70" x-show="count" x-cloak>
                <template x-for="line in lines" :key="line.id">
                    <li class="flex items-center gap-3 py-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-espresso-900" x-text="line.name"></p>
                            <p class="text-xs text-roast tabular-nums" x-text="rp(line.price) + ' × ' + line.qty + ' = ' + rp(line.price * line.qty)"></p>
                        </div>
                        <div class="flex items-center rounded-full bg-cream ring-1 ring-latte">
                            <button type="button" x-on:click="dec(line.id)" class="flex size-9 items-center justify-center rounded-full text-espresso active:bg-latte" aria-label="Kurangi"><x-icon name="minus" class="size-4" /></button>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" :value="line.qty"
                                x-on:focus="$event.target.select()"
                                x-on:change="setQty(line.id, $event.target.value); $event.target.value = qty(line.id)"
                                x-on:keydown.enter.prevent="$event.target.blur()"
                                class="h-9 w-12 rounded-lg bg-white text-center text-base font-semibold tabular-nums text-espresso-900 ring-1 ring-latte focus:outline-none focus:ring-2 focus:ring-caramel"
                                :aria-label="'Jumlah ' + line.name">
                            <button type="button" x-on:click="add(line.id)" class="flex size-9 items-center justify-center rounded-full text-espresso active:bg-latte" aria-label="Tambah"><x-icon name="plus" class="size-4" /></button>
                        </div>
                    </li>
                </template>
            </ul>
            <p x-show="!count" class="rounded-xl bg-cream px-3 py-6 text-center text-sm text-roast">Belum ada produk. Ketuk produk untuk menambahkan.</p>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="trx-discount" class="label">Diskon (Rp)</label>
                    <input id="trx-discount" type="text" inputmode="numeric" x-model="discount" x-on:input="discount = digits($event.target.value)" class="input tabular-nums" placeholder="0">
                </div>
                <div>
                    <label for="trx-dp" class="label">DP (Rp)</label>
                    <input id="trx-dp" type="text" inputmode="numeric" x-model="downpayment" x-on:input="downpayment = digits($event.target.value)" class="input tabular-nums" placeholder="0">
                </div>
            </div>

            <dl class="space-y-1.5 rounded-2xl bg-cream p-4 text-sm">
                <div class="flex justify-between"><dt class="text-roast">Total</dt><dd class="tabular-nums" x-text="rp(total)"></dd></div>
                <div class="flex justify-between" x-show="discountValue" x-cloak><dt class="text-roast">Diskon</dt><dd class="tabular-nums" x-text="'− ' + rp(discountValue)"></dd></div>
                <div class="flex justify-between" x-show="dpValue" x-cloak><dt class="text-roast">DP</dt><dd class="tabular-nums" x-text="'− ' + rp(dpValue)"></dd></div>
                <div class="flex items-baseline justify-between border-t border-latte pt-2">
                    <dt class="font-semibold text-espresso-900">Bayar</dt>
                    <dd class="font-display text-2xl font-semibold tabular-nums text-espresso-900" x-text="rp(due)"></dd>
                </div>
            </dl>

            @if ($transaction)
                <button type="button" wire:click="delete" wire:confirm="Hapus transaksi ini? Transaksi tidak akan muncul lagi di daftar maupun laporan."
                    class="flex w-full items-center justify-center gap-2 py-2 text-sm font-medium text-cherry">
                    <x-icon name="trash" class="size-4" /> Hapus transaksi
                </button>
            @endif
        </div>

        <div class="flex gap-2 border-t border-latte px-5 pb-[calc(1rem+env(safe-area-inset-bottom))] pt-3 lg:pb-4">
            <button type="button" x-on:click="save()" :disabled="busy || !count"
                class="btn btn-primary h-14 w-full justify-between px-5 text-base">
                <span class="flex items-center gap-2">
                    <x-icon name="check" class="size-5" />
                    <span x-text="busy ? 'Menyimpan…' : '{{ $transaction?->isPaid() ? 'Simpan perubahan' : 'Checkout' }}'"></span>
                </span>
                <span class="text-right leading-tight">
                    <span class="block text-xs font-medium text-foam/70" x-text="count + ' item'"></span>
                    <span class="block font-display text-lg tabular-nums" x-text="rp(due)"></span>
                </span>
            </button>
        </div>
    </aside>
</div>

@script
<script>
    Alpine.data('posCart', (products, bestSellers, initial) => ({
        products,
        bestSellers,
        cart: { ...initial.cart },
        customer: initial.customer,
        address: initial.address ?? '',
        phone: initial.phone ?? '',
        customerId: initial.customerId ?? null,
        pickedName: initial.customerId ? initial.customer : null,
        suggestions: [],
        highlight: 0,
        discount: String(initial.discount ?? ''),
        downpayment: String(initial.downpayment ?? ''),
        storeId: initial.storeId ?? '',
        search: '',
        category: 'Semua',
        sheet: false,
        busy: false,

        get byId() {
            return Object.fromEntries(this.products.map(p => [p.id, p]))
        },
        get filtered() {
            let term = this.search.trim().toLowerCase()
            if (term) return this.products.filter(p => p.name.toLowerCase().includes(term))
            if (this.category === 'Terlaris') return this.bestSellers.map(id => this.byId[id]).filter(Boolean)
            if (this.category === 'Semua') return this.products
            return this.products.filter(p => p.categories.includes(this.category))
        },
        get lines() {
            return Object.entries(this.cart)
                .filter(([id, qty]) => qty > 0 && this.byId[id])
                .map(([id, qty]) => ({ ...this.byId[id], qty }))
        },
        get count() {
            return Object.values(this.cart).reduce((sum, qty) => sum + qty, 0)
        },
        get total() {
            return this.lines.reduce((sum, line) => sum + line.price * line.qty, 0)
        },
        get discountValue() {
            return Math.min(Number(this.discount) || 0, this.total)
        },
        get dpValue() {
            return Math.min(Number(this.downpayment) || 0, this.total - this.discountValue)
        },
        get due() {
            return this.total - this.discountValue - this.dpValue
        },
        qty(id) {
            return this.cart[id] || 0
        },
        add(id) {
            this.cart[id] = Math.min(999, this.qty(id) + 1)
        },
        async lookupCustomers() {
            // Editing the name after picking a customer unlinks it.
            if (this.customerId && this.customer !== this.pickedName) this.customerId = null
            let term = this.customer.trim()
            this.highlight = 0
            this.suggestions = term.length >= 2 ? await $wire.searchCustomers(term) : []
        },
        pickCustomer(c) {
            this.customer = c.name
            this.pickedName = c.name
            this.customerId = c.id
            this.address = c.address || ''
            this.phone = c.phone || ''
            this.suggestions = []
        },
        setQty(id, value) {
            let n = parseInt(String(value).replace(/\D/g, ''), 10) || 0
            if (n > 0) this.cart[id] = Math.min(999, n)
            else delete this.cart[id]
            if (this.count === 0) this.sheet = false
        },
        dec(id) {
            let next = this.qty(id) - 1
            if (next > 0) this.cart[id] = next
            else delete this.cart[id]
            if (this.count === 0) this.sheet = false
        },
        digits(value) {
            return String(value).replace(/\D/g, '').slice(0, 9)
        },
        rp(n) {
            return 'Rp' + Number(n || 0).toLocaleString('id-ID')
        },
        async save() {
            if (this.busy || !this.count) return
            this.busy = true
            try {
                await $wire.save(this.cart, this.customer, this.discount, this.downpayment, this.storeId, this.address, this.customerId, this.phone)
            } finally {
                this.busy = false
            }
        },
    }))
</script>
@endscript
