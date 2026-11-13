<?php

namespace App\Services\Mobile;

use App\Models\AccountDeletionRequest;
use App\Models\Address;
use App\Models\BuyerNotification;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Fulfillment;
use App\Models\FulfillmentEvent;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductReviewMedia;
use App\Models\ProductVariant;
use App\Models\PsgcBarangay;
use App\Models\PsgcCityMunicipality;
use App\Models\PsgcProvince;
use App\Models\PsgcRegion;
use App\Models\ReturnRequest;
use App\Models\RiderEarning;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicIds
{
    public const MODELS = [User::class, Product::class, ProductVariant::class, Shop::class, Category::class, Address::class, Order::class, OrderItem::class, Fulfillment::class, FulfillmentEvent::class, RiderEarning::class, AccountDeletionRequest::class, PsgcRegion::class, PsgcProvince::class, PsgcCityMunicipality::class, PsgcBarangay::class, Conversation::class, Message::class, ProductReview::class, ProductReviewMedia::class, ReturnRequest::class, BuyerNotification::class];

    public static function id(?Model $model): ?string
    {
        if (! $model) {
            return null;
        }
        if (! $model->public_id) {
            // Covers raw geography seed inserts that bypass model observers.
            $value = (string) Str::uuid();
            DB::table($model->getTable())->where('id', $model->getKey())->whereNull('public_id')->update(['public_id' => $value]);
            $model->setAttribute('public_id', DB::table($model->getTable())->where('id', $model->getKey())->value('public_id'));
        }

        return $model->public_id;
    }

    public static function primeGraph($models): void
    {
        $queue = collect($models)->values()->all();
        $seen = [];
        $groups = [];
        while ($model = array_pop($queue)) {
            if (! $model instanceof Model || isset($seen[$key = $model->getTable().':'.$model->getKey()])) {
                continue;
            }
            $seen[$key] = true;
            if (array_key_exists('public_id', $model->getAttributes()) && ! $model->public_id) {
                $groups[$model->getTable()][$model->id] = $model;
            }
            foreach ($model->getRelations() as $relation) {
                if ($relation instanceof Model) {
                    $queue[] = $relation;
                } elseif ($relation instanceof Collection) {
                    array_push($queue, ...$relation->all());
                }
            }
        }
        foreach ($groups as $table => $records) {
            foreach (array_chunk($records, 200, true) as $chunk) {
                $ids = array_keys($chunk);
                $bindings = [];
                $cases = [];
                foreach ($ids as $id) {
                    $cases[] = 'WHEN ? THEN ?';
                    $bindings[] = $id;
                    $bindings[] = (string) Str::uuid();
                }
                $quoted = DB::connection()->getQueryGrammar()->wrapTable($table);
                DB::update('UPDATE '.$quoted.' SET public_id = CASE id '.implode(' ', $cases).' END WHERE public_id IS NULL AND id IN ('.implode(',', array_fill(0, count($ids), '?')).')', [...$bindings, ...$ids]);
                $actual = DB::table($table)->whereIn('id', $ids)->pluck('public_id', 'id');
                foreach ($chunk as $id => $model) {
                    $model->setAttribute('public_id', $actual[$id]);
                }
            }
        }
    }

    public static function find(string $model, string $id): Model
    {
        abort_unless(Str::isUuid($id), 404);

        return $model::query()->where('public_id', $id)->firstOrFail();
    }
}
