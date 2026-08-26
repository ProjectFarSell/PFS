<?php

namespace App\Providers;

use App\Support\Cart;
use App\Support\GuestSession;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            $view->with('cartCount', Cart::count());
            $view->with('isGuestBrowse', GuestSession::active());
        });
    }
}
