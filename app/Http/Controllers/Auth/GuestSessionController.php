<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\GuestSession;
use Illuminate\Http\RedirectResponse;

class GuestSessionController extends Controller
{
    public function store(): RedirectResponse
    {
        GuestSession::start();

        // Browsing as a guest must not bounce back into an authenticated route.
        session()->forget('url.intended');

        return redirect()->route('home')->with('status', 'Browsing as guest. Cart is saved on this device.');
    }
}
