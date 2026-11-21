<?php

use App\Models\Transaction;
use App\Support\StoreContext;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Unduh Riwayat')] class extends Component
{
    public string $month = '';

    public function mount(): void
    {
        $this->month = now()->format('Ym');
    }

    public function with(): array
    {
        $context = StoreContext::for();
        $first = Transaction::forStores($context->storeIds())->min('created_at');
        $start = $first ? \Carbon\CarbonImmutable::parse($first)->startOfMonth() : now()->toImmutable()->startOfMonth();

        $months = [];
        for ($m = now()->toImmutable()->startOfMonth(); $m->gte($start); $m = $m->subMonth()) {
            $months[$m->format('Ym')] = $m->translatedFormat('F Y');
        }

        return ['months' => $months, 'storeLabel' => $context->label()];
    }
};
?>

<div class="mx-auto max-w-xl">
    <h1 class="page-title">Unduh Riwayat</h1>
    <p class="mt-1 text-sm text-roast">File Excel berisi semua transaksi pada bulan yang dipilih.</p>

    <form method="GET" action="{{ route('admin.history.download') }}" class="card mt-4 space-y-4 p-4 sm:p-6">
        <div>
            <label for="bulan" class="label">Bulan</label>
            <select id="bulan" name="bulan" wire:model="month" class="input">
                @foreach ($months as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <p class="flex items-center gap-2 rounded-xl bg-cream px-3 py-2.5 text-sm text-espresso-800">
            <x-icon name="store" class="size-4 text-caramel-600" /> Toko: <strong class="font-semibold">{{ $storeLabel }}</strong>
            <span class="text-roast">(ubah lewat pilihan toko di atas)</span>
        </p>

        <button type="submit" class="btn btn-primary h-12 w-full text-base"><x-icon name="download" class="size-5" /> Unduh Excel</button>

        <p class="text-xs text-roast">Kolom: Date, Time, User, Customer, Total_Item, Total_Price, Discount, Status — sama seperti versi lama.</p>
    </form>
</div>
