<?php

namespace App\Providers;

use App\Contracts\DeliveryFeeService;
use App\Models\Category;
use App\Services\Checkout\FlatRateDeliveryFeeService;
use App\Support\Cart;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DeliveryFeeService::class, fn () => new FlatRateDeliveryFeeService(
            (float) config('commerce.delivery_fee', 49.00)
        ));
    }

    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $view->with('cartCount', Cart::count());
            $view->with('navCategories', Category::query()->orderBy('sort_order')->get());
        });
    }
}
