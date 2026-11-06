<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\DeliveryAttempt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\RiderProfile;
use App\Models\SellerApplication;
use App\Models\Shop;
use App\Models\User;
use App\Services\Security\AuditTrail;
use Illuminate\Database\Eloquent\Model;

class SecurityAuditObserver
{
    private const FIELDS = [
        Category::class => ['name', 'slug', 'sort_order'],
        Product::class => ['stock', 'price', 'is_active', 'category_id', 'sku', 'has_variants'],
        ProductVariant::class => ['stock', 'price_override', 'is_active', 'sku', 'options'],
        SellerApplication::class => ['status', 'reviewed_by', 'reviewed_at'],
        RiderProfile::class => ['status', 'reviewed_by', 'reviewed_at'],
        Shop::class => ['is_active'],
        ReturnRequest::class => ['status', 'approved_amount', 'return_required', 'return_received_at', 'refunded_at'],
        User::class => ['role', 'suspended_at', 'security_version', 'mfa_confirmed_at'],
        DeliveryAttempt::class => ['fulfillment_id', 'number', 'reason', 'contact_method'],
    ];

    public function created(Model $model): void
    {
        $this->write($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->write($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->write($model, 'deleted');
    }

    private function write(Model $model, string $event): void
    {
        if (! auth()->check()) {
            return;
        }
        $changes = [];
        foreach (self::FIELDS[$model::class] ?? [] as $field) {
            if ($event === 'updated' && ! $model->wasChanged($field)) {
                continue;
            }
            $changes[$field] = ['before' => $event === 'created' ? null : $model->getRawOriginal($field), 'after' => $event === 'deleted' ? null : $model->getAttributes()[$field] ?? null];
        }
        if ($changes !== []) {
            app(AuditTrail::class)->record(class_basename($model).'.'.$event, $model, $changes);
        }
    }
}
