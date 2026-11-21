<?php

namespace App\Support;

use App\Models\Store;
use App\Models\User;

/**
 * The store chosen in the header selector, kept in the session.
 * `null` means "all stores" (only for users who can access every store).
 */
class StoreContext
{
    public const ALL = 'all';

    private const SESSION_KEY = 'store_context';

    public function __construct(private User $user) {}

    public static function for(?User $user = null): self
    {
        return new self($user ?? auth()->user());
    }

    /** Selected store id, or null for all stores. */
    public function currentId(): ?int
    {
        $value = session(self::SESSION_KEY);

        if ($value === self::ALL && $this->user->hasAllStores()) {
            return null;
        }

        if (is_numeric($value) && $this->user->canAccessStore((int) $value)) {
            return (int) $value;
        }

        // Default: all stores when allowed, otherwise the first assigned store.
        return $this->user->hasAllStores() ? null : $this->user->accessibleStores()->first()?->id;
    }

    public function current(): ?Store
    {
        $id = $this->currentId();

        return $id ? $this->user->accessibleStores()->firstWhere('id', $id) : null;
    }

    public function isAll(): bool
    {
        return $this->currentId() === null && $this->user->hasAllStores();
    }

    /**
     * Store ids the current views should include. For admins, "all stores"
     * also covers deleted stores so their past sales stay in reports.
     *
     * @return array<int>
     */
    public function storeIds(): array
    {
        $id = $this->currentId();

        if ($id) {
            return [$id];
        }

        return $this->user->isAdmin()
            ? Store::withTrashed()->pluck('id')->all()
            : $this->user->accessibleStores()->pluck('id')->all();
    }

    public function select(string $value): bool
    {
        if ($value === self::ALL && $this->user->hasAllStores()) {
            session([self::SESSION_KEY => self::ALL]);

            return true;
        }

        if (is_numeric($value) && $this->user->canAccessStore((int) $value)) {
            session([self::SESSION_KEY => (int) $value]);

            return true;
        }

        return false;
    }

    public function label(): string
    {
        return $this->isAll() ? 'Semua toko' : ($this->current()?->name ?? 'Tanpa toko');
    }
}
