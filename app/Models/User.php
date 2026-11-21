<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_USER = 'user';

    /** @var Collection<int, Store>|null */
    private ?Collection $accessibleStoresCache = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Stores explicitly assigned to a non-admin user. */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Stores this user may work in: every store for admins, otherwise the
     * assigned ones.
     *
     * @return Collection<int, Store>
     */
    public function accessibleStores(): Collection
    {
        return $this->accessibleStoresCache ??= $this->isAdmin()
            ? Store::orderBy('name')->get()
            : $this->stores()->orderBy('name')->get();
    }

    /** Admins may also open records of deleted stores (old receipts). */
    public function canAccessStore(int $storeId): bool
    {
        if ($this->isAdmin()) {
            return Store::withTrashed()->whereKey($storeId)->exists();
        }

        return $this->accessibleStores()->contains('id', $storeId);
    }

    /** Whether the "all stores" view is available to this user. */
    public function hasAllStores(): bool
    {
        return $this->isAdmin()
            || ($this->accessibleStores()->isNotEmpty() && $this->accessibleStores()->count() === Store::count());
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr(trim($this->name), 0, 1)) ?: '?';
    }
}
