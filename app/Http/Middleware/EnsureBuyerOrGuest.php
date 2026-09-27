<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBuyerOrGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->role === UserRole::Buyer) {
            return $next($request);
        }

        $destination = match ($user->role) {
            UserRole::Seller => 'seller.dashboard',
            UserRole::Rider => 'rider.dashboard',
            UserRole::Admin => 'admin.dashboard',
            default => 'home',
        };

        return to_route($destination)->with('status', 'Shopping actions are available to buyer accounts only.');
    }
}
