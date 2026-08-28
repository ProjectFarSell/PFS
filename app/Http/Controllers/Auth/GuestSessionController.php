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

        return redirect()->intended(route('home'))->with('status', 'Browsing as guest. Cart is saved on this device.');
    }
}
