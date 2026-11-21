<?php

use App\Models\PaymentAccount;
use App\Models\Transaction;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Struk')] class extends Component
{
    #[Locked]
    public int $transactionId;

    public bool $justPaid = false;

    public function mount(Transaction $transaction): void
    {
        abort_unless(auth()->user()->canAccessStore($transaction->store_id), 403);

        $this->transactionId = $transaction->id;
    }

    /** "Lunas": the customer has paid. */
    public function markPaid(): void
    {
        $transaction = Transaction::findOrFail($this->transactionId);
        abort_unless(auth()->user()->canAccessStore($transaction->store_id), 403);

        if (! $transaction->isPaid()) {
            $transaction->update(['status' => Transaction::STATUS_PAID]);
            $this->justPaid = true;
        }
    }

    public function with(): array
    {
        $trx = Transaction::with(['store', 'user:id,name', 'customerRecord:id,address,phone', 'lines.product:id,name'])->findOrFail($this->transactionId);

        return [
            'trx' => $trx,
            'accounts' => $trx->isPaid() ? collect() : PaymentAccount::orderBy('id')->get(),
        ];
    }
};
?>

<div class="mx-auto max-w-md"
     x-data="{
        sharing: false,
        note: '',
        async share() {
            if (this.sharing) return
            this.sharing = true
            this.note = ''
            try {
                let result = await window.shareReceiptImage(this.$refs.receipt, @js($trx->invoiceNumber().'.png'), @js('Struk Kopi Keto '.$trx->invoiceNumber()))
                if (result === 'downloaded') this.note = 'Perangkat ini tidak mendukung bagikan langsung. Gambar struk sudah diunduh, kirim lewat WhatsApp secara manual.'
            } catch (e) {
                this.note = 'Gagal membuat gambar struk. Coba lagi.'
            } finally {
                this.sharing = false
            }
        },
        copied: null,
        copy(text, id) {
            navigator.clipboard?.writeText(text)
            this.copied = id
            setTimeout(() => this.copied = null, 1500)
        },
     }">

    {{-- Title / status --}}
    @if ($justPaid)
        <div class="mb-5 flex flex-col items-center text-center" data-print-hide>
            <span class="flex size-16 items-center justify-center rounded-full bg-espresso text-caramel-300 shadow-lg shadow-espresso/25">
                <x-icon name="check" class="size-9" />
            </span>
            <h1 class="mt-3 font-display text-2xl font-semibold text-espresso-900">Lunas</h1>
            <p class="text-sm text-roast">Pembayaran {{ rupiah($trx->amountDue()) }} tercatat.</p>
        </div>
    @else
        <div class="mb-4 flex items-center gap-2" data-print-hide>
            <a href="{{ route('transactions.index', $trx->isPaid() ? ['tab' => 'lunas'] : []) }}" class="btn-icon -ml-2" aria-label="Kembali"><x-icon name="arrow-left" /></a>
            <h1 class="flex-1 font-display text-xl font-semibold text-espresso-900">Struk</h1>
            <span @class(['chip py-1', 'bg-espresso text-foam' => $trx->isPaid(), 'bg-caramel-100 text-caramel-700 ring-1 ring-caramel-300/60' => ! $trx->isPaid()])>
                {{ $trx->isPaid() ? 'Lunas' : 'Belum lunas' }}
            </span>
        </div>
    @endif

    {{-- Primary actions, above the receipt --}}
    <div class="mb-4 space-y-2" data-print-hide>
        <div @class(['grid gap-2', 'grid-cols-2' => ! $trx->isPaid(), 'grid-cols-1' => $trx->isPaid()])>
            <button type="button" x-on:click="share()" :disabled="sharing"
                class="btn h-14 bg-[#1f8f4e] text-base text-white shadow-sm shadow-[#1f8f4e]/30 hover:bg-[#187a42]">
                <svg x-show="!sharing" class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.2a9.7 9.7 0 0 0-8.3 14.8L2.4 21.6l4.7-1.2A9.7 9.7 0 1 0 12 2.2Zm0 17.7a8 8 0 0 1-4.1-1.1l-.3-.2-2.8.7.8-2.7-.2-.3A8 8 0 1 1 12 19.9Zm4.4-6c-.2-.1-1.4-.7-1.7-.8-.2-.1-.4-.1-.5.1l-.8 1c-.1.2-.3.2-.5.1a6.6 6.6 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.5-.4h-.5a.9.9 0 0 0-.6.3 2.7 2.7 0 0 0-.9 2c0 1.2.9 2.4 1 2.5.1.2 1.7 2.7 4.2 3.8 1.6.7 2.2.7 3 .6.5-.1 1.4-.6 1.6-1.1.2-.6.2-1 .1-1.1l-.5-.3Z"/></svg>
                <span x-text="sharing ? 'Menyiapkan…' : 'Kirim WA'">Kirim WA</span>
            </button>

            @unless ($trx->isPaid())
                <button type="button" wire:click="markPaid" wire:confirm="Tandai transaksi ini sudah lunas ({{ rupiah($trx->amountDue()) }})?"
                    class="btn btn-primary h-14 text-base" wire:loading.attr="disabled" wire:target="markPaid">
                    <x-icon name="check" class="size-5" /> Lunas
                </button>
            @endunless
        </div>

        <p x-show="note" x-cloak x-text="note" class="rounded-xl bg-cream px-3 py-2 text-sm text-espresso-800"></p>
    </div>

    {{-- Receipt (this element becomes the shared image) --}}
    <article x-ref="receipt" class="relative rounded-2xl bg-white px-5 py-6 font-mono text-[13px] leading-relaxed text-espresso-950 ring-1 ring-latte print:rounded-none print:px-0 print:ring-0">
        <header class="text-center">
            <img src="{{ asset('images/logo.png') }}" alt="Kopi Keto" class="mx-auto h-7 w-auto">
            <p class="mt-2 font-sans text-sm font-semibold">{{ $trx->store?->name }}</p>
            @if ($trx->store?->address)<p class="text-xs text-roast print:text-black">{{ $trx->store->address }}</p>@endif
            @if ($trx->store?->phone)<p class="text-xs text-roast print:text-black">{{ $trx->store->phone }}</p>@endif
        </header>

        <div class="my-4 border-t border-dashed border-espresso/30"></div>

        <div class="flex justify-between gap-2">
            <span>{{ $trx->created_at->format('d/m/Y H:i') }}</span>
            <span class="font-bold">{{ $trx->invoiceNumber() }}</span>
        </div>
        @if ($trx->customer)<p>Pelanggan: {{ $trx->customer }}</p>@endif
        @if ($trx->customerRecord?->address)<p class="whitespace-pre-line">Alamat: {{ $trx->customerRecord->address }}</p>@endif
        @if ($trx->customerRecord?->phone)<p>HP: {{ $trx->customerRecord->phone }}</p>@endif
        @if ($trx->user)<p class="text-roast print:text-black">Kasir: {{ $trx->user->name }}</p>@endif

        <div class="my-3 border-t border-dashed border-espresso/30"></div>

        <ul class="space-y-1.5">
            @foreach ($trx->lines as $line)
                <li>
                    <p>{{ $line->product?->name ?? 'Produk #'.$line->product_id }}</p>
                    <p class="flex justify-between pl-3 tabular-nums">
                        <span>{{ $line->amount }} × {{ number_format($line->price, 0, ',', '.') }}</span>
                        <span>{{ number_format($line->subtotal(), 0, ',', '.') }}</span>
                    </p>
                </li>
            @endforeach
        </ul>

        <div class="my-3 border-t border-dashed border-espresso/30"></div>

        <dl class="space-y-0.5 tabular-nums">
            <div class="flex justify-between"><dt>TOTAL</dt><dd>{{ number_format($trx->total_price, 0, ',', '.') }}</dd></div>
            @if ($trx->discount)<div class="flex justify-between"><dt>DISKON</dt><dd>−{{ number_format($trx->discount, 0, ',', '.') }}</dd></div>@endif
            @if ($trx->downpayment)<div class="flex justify-between"><dt>DP</dt><dd>−{{ number_format($trx->downpayment, 0, ',', '.') }}</dd></div>@endif
            <div class="flex justify-between pt-1 text-[15px] font-bold"><dt>{{ $trx->isPaid() ? 'DIBAYAR' : 'TAGIHAN' }}</dt><dd>{{ number_format($trx->amountDue(), 0, ',', '.') }}</dd></div>
        </dl>

        <p @class([
            'mx-auto mt-3 w-fit rounded-full px-3 py-0.5 text-center font-sans text-xs font-bold uppercase tracking-wider',
            'bg-espresso text-foam' => $trx->isPaid(),
            'bg-caramel-100 text-caramel-700' => ! $trx->isPaid(),
        ])>{{ $trx->isPaid() ? 'Lunas' : 'Belum lunas' }}</p>

        @if ($accounts->isNotEmpty())
            <div class="my-4 border-t border-dashed border-espresso/30"></div>
            <section>
                <p class="text-center font-sans text-xs font-semibold uppercase tracking-wider text-roast print:text-black">Pembayaran via transfer</p>
                <ul class="mt-2 space-y-2">
                    @foreach ($accounts as $account)
                        <li class="flex items-center gap-2 rounded-xl bg-cream px-3 py-2 print:bg-transparent print:px-0">
                            <div class="min-w-0 flex-1">
                                <p class="font-sans text-xs font-semibold">{{ $account->bank }}</p>
                                <p class="text-[15px] font-bold tracking-wide">{{ $account->account_number }}</p>
                                <p class="font-sans text-xs">a.n. {{ $account->account_owner }}</p>
                            </div>
                            <button type="button" data-capture-hide data-print-hide
                                x-on:click="copy(@js($account->account_number), {{ $account->id }})"
                                class="flex h-9 shrink-0 items-center gap-1 rounded-full bg-white px-3 font-sans text-xs font-medium text-espresso ring-1 ring-latte active:scale-95">
                                <x-icon name="copy" class="size-4" />
                                <span x-text="copied === {{ $account->id }} ? 'Tersalin' : 'Salin'">Salin</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="my-4 border-t border-dashed border-espresso/30"></div>

        <footer class="text-center text-xs">
            <p>Terima kasih sudah berbelanja di Kopi Keto</p>
            <p>Salam Kopi Nusantara</p>
        </footer>
    </article>

    {{-- Actions --}}
    <div class="mt-4 space-y-2" data-print-hide>
        <div class="grid grid-cols-2 gap-2">
            <button type="button" onclick="window.print()" class="btn btn-secondary h-12"><x-icon name="printer" class="size-5" /> Cetak</button>
            <a href="{{ route('transactions.edit', $trx) }}" class="btn btn-secondary h-12"><x-icon name="pencil" class="size-5" /> Ubah</a>
        </div>

        @if ($justPaid)
            <a href="{{ route('transactions.create') }}" class="btn btn-secondary h-12 w-full"><x-icon name="plus" class="size-5" /> Transaksi berikutnya</a>
        @endif
    </div>
</div>
