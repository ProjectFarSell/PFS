<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'verification_channel',
        'avatar_path',
        'google_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar_path && Storage::disk('public')->exists($this->avatar_path)) {
            return Storage::disk('public')->url($this->avatar_path);
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&color=FFFFFF&background=282832&bold=true';
    }

    public function getInitialsAttribute(): string
    {
        return collect(explode(' ', $this->name))
            ->map(fn ($segment) => mb_substr($segment, 0, 1))
            ->take(2)
            ->join('');
    }

    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class);
    }

    public function sellerApplication(): HasOne
    {
        return $this->hasOne(SellerApplication::class);
    }

    public function riderProfile(): HasOne
    {
        return $this->hasOne(RiderProfile::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function savedItems(): HasMany
    {
        return $this->hasMany(SavedItem::class);
    }

    public function verificationChallenges(): HasMany
    {
        return $this->hasMany(VerificationChallenge::class);
    }

    public function hasSavedProduct(int $productId): bool
    {
        return $this->savedItems()->where('product_id', $productId)->exists();
    }

    public function isSeller(): bool
    {
        return $this->role === UserRole::Seller;
    }

    public function isRider(): bool
    {
        return $this->role === UserRole::Rider;
    }
}
