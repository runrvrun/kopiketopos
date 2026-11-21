<?php

use App\Models\Store;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengguna')] class extends Component
{
    #[Locked]
    public ?int $userId = null;

    public string $name = '';
    public string $email = '';
    public string $role = User::ROLE_USER;
    public string $password = '';
    /** @var array<int> */
    public array $storeIds = [];

    public function mount(?User $user = null): void
    {
        if ($user?->exists) {
            $this->userId = $user->id;
            $this->fill($user->only('name', 'email', 'role'));
            $this->storeIds = $user->stores()->pluck('stores.id')->all();
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->userId)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])],
            'password' => [$this->userId ? 'nullable' : 'required', Password::min(8)],
            'storeIds' => ['array'],
            'storeIds.*' => ['integer', 'exists:stores,id'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah dipakai akun lain.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->userId === auth()->id() && $data['role'] !== User::ROLE_ADMIN) {
            $this->addError('role', 'Anda tidak bisa menurunkan peran akun sendiri.');

            return;
        }

        $user = $this->userId ? User::findOrFail($this->userId) : new User;
        $user->fill(collect($data)->only('name', 'email', 'role')->all());
        if (filled($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        // Admins access every store by role; keep explicit rows only for staff.
        $user->stores()->sync($user->isAdmin() ? [] : $data['storeIds']);

        session()->flash('status', $this->userId ? 'Pengguna diperbarui' : 'Pengguna ditambahkan');
        $this->redirectRoute('admin.users.index');
    }

    public function delete(): void
    {
        $user = User::withCount('transactions')->findOrFail($this->userId);

        if ($user->id === auth()->id()) {
            $this->addError('delete', 'Anda tidak bisa menghapus akun sendiri.');

            return;
        }

        if ($user->transactions_count > 0) {
            $this->addError('delete', "Pengguna ini sudah mencatat {$user->transactions_count} transaksi, jadi tidak bisa dihapus agar riwayat tetap utuh. Ganti kata sandinya untuk menonaktifkan akses.");

            return;
        }

        $user->delete();
        session()->flash('status', 'Pengguna dihapus');
        $this->redirectRoute('admin.users.index');
    }

    public function with(): array
    {
        return ['stores' => Store::orderBy('name')->get()];
    }
};
?>

<div class="mx-auto max-w-xl">
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.users.index') }}" class="btn-icon -ml-2" aria-label="Kembali"><x-icon name="arrow-left" /></a>
        <h1 class="page-title">{{ $userId ? 'Ubah pengguna' : 'Tambah pengguna' }}</h1>
    </div>

    <form wire:submit="save" class="card mt-4 space-y-4 p-4 sm:p-6" novalidate>
        <div>
            <label for="name" class="label">Nama</label>
            <input id="name" type="text" wire:model="name" class="input" autocomplete="off">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" type="email" inputmode="email" autocapitalize="none" wire:model="email" class="input" autocomplete="off">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="label">Kata sandi @if ($userId)<span class="font-normal text-roast">(kosongkan jika tidak diubah)</span>@endif</label>
            <input id="password" type="password" wire:model="password" class="input" autocomplete="new-password">
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <fieldset>
            <legend class="label">Peran</legend>
            <div class="grid grid-cols-2 gap-2">
                @foreach ([User::ROLE_USER => ['Staf', 'Hanya toko yang dipilih'], User::ROLE_ADMIN => ['Admin', 'Semua toko & menu admin']] as $value => [$label, $hint])
                    <label @class(['flex cursor-pointer flex-col rounded-xl p-3 ring-1 transition', 'bg-caramel-100 ring-2 ring-caramel' => $role === $value, 'bg-white ring-latte' => $role !== $value])>
                        <input type="radio" wire:model.live="role" value="{{ $value }}" class="sr-only">
                        <span class="text-sm font-semibold text-espresso-900">{{ $label }}</span>
                        <span class="text-xs text-roast">{{ $hint }}</span>
                    </label>
                @endforeach
            </div>
            @error('role') <p class="field-error">{{ $message }}</p> @enderror
        </fieldset>

        <fieldset>
            <legend class="label">Akses toko</legend>
            @if ($role === User::ROLE_ADMIN)
                <p class="rounded-xl bg-cream px-3 py-2.5 text-sm text-roast">Admin otomatis bisa mengakses semua toko.</p>
            @else
                <div class="space-y-1">
                    @forelse ($stores as $store)
                        <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl px-1 text-sm text-espresso-900">
                            <input type="checkbox" wire:model="storeIds" value="{{ $store->id }}" class="size-5 rounded-md accent-espresso">
                            {{ $store->name }}
                            @unless ($store->is_active)<span class="text-xs text-roast">(nonaktif)</span>@endunless
                        </label>
                    @empty
                        <p class="text-sm text-roast">Belum ada toko.</p>
                    @endforelse
                </div>
            @endif
        </fieldset>

        <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-between">
            @if ($userId)
                <button type="button" wire:click="delete" wire:confirm="Hapus pengguna ini?" class="btn btn-danger"><x-icon name="trash" class="size-4" /> Hapus</button>
            @else
                <span></span>
            @endif
            <button type="submit" class="btn btn-primary h-12 sm:h-11" wire:loading.attr="disabled"><x-icon name="check" class="size-4" /> Simpan</button>
        </div>
        @error('delete') <p class="rounded-xl bg-cherry/10 px-3 py-2 text-sm text-cherry">{{ $message }}</p> @enderror
    </form>
</div>
