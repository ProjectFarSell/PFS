<?php

namespace App\Models;

use App\Enums\RiderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiderProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'vehicle_type',
        'plate_number',
        'license_no',
        'city',
        'bio',
        'reviewed_at',
        'reviewed_by',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => RiderStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Order::class, 'rider_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RiderDocument::class);
    }

    public function isApproved(): bool
    {
        return $this->status === RiderStatus::Approved;
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reviewToken(): string
    {
        // Detect edits/uploads since the admin opened the review page.
        return hash('sha256', json_encode([
            $this->getAttributes(),
            $this->documents()->orderBy('id')->get()->toArray(),
        ], JSON_THROW_ON_ERROR));
    }
}
