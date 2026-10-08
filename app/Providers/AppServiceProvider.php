<?php

namespace App\Providers;

use App\Contracts\DeliveryFeeService;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Checkout\FlatRateDeliveryFeeService;
use App\Support\Cart;
use App\Enums\UserRole;
use Illuminate\Support\Facades\URL;
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
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        View::composer('layouts.app', function ($view): void {
            $view->with('cartCount', Cart::count());
            $view->with('navCategories', Category::query()->orderBy('sort_order')->get());
        });

        View::composer(['layouts.app', 'layouts.portal'], function ($view): void {
            $user = auth()->user();
            $unreadCount = 0;
            if ($user?->role === UserRole::Buyer) {
                $conversationIds = Conversation::query()->select('id')->where('buyer_id', $user->id);
                $unreadCount = Message::query()->whereIn('conversation_id', $conversationIds)->whereNull('read_at')->where('sender_id', '!=', $user->id)->count();
            } elseif ($user?->role === UserRole::Seller && $user->shop) {
                $conversationIds = Conversation::query()->select('id')->where('shop_id', $user->shop->id);
                $unreadCount = Message::query()->whereIn('conversation_id', $conversationIds)->whereNull('read_at')->where('sender_id', '!=', $user->id)->count();
            }
            $view->with('chatUnreadCount', $unreadCount);
        });
    }
}
