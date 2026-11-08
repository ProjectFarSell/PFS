<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierBooking extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['details'];

    protected function casts(): array
    {
        return ['details' => 'encrypted:array', 'quote_expires_at' => 'datetime', 'booked_at' => 'datetime', 'last_synced_at' => 'datetime', 'cost_cents' => 'integer'];
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function isSandbox(): bool
    {
        return $this->mode === 'api' && $this->api_environment === 'sandbox';
    }

    public function blocksCancellation(): bool
    {
        return in_array($this->status, ['submitting', 'unknown', 'booked'], true);
    }
}
