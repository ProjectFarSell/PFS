<?php

namespace App\Support;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class GuestSession
{
    public const KEY = 'farsell.guest_id';

    public static function start(): string
    {
        if (! Session::has(self::KEY)) {
            Session::put(self::KEY, (string) Str::uuid());
        }

        return Session::get(self::KEY);
    }

    public static function id(): ?string
    {
        return Session::get(self::KEY);
    }

    public static function active(): bool
    {
        return Session::has(self::KEY) && auth()->guest();
    }

    public static function forget(): void
    {
        Session::forget(self::KEY);
    }
}
