<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    public const MESSAGE = 'If an account matches that email, we have sent a password reset link. Check your inbox and spam folder. Wait a minute before requesting another.';

    public function create()
    {
        return response()->view('auth.forgot-password')->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:180']]);
        $email = User::whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->value('email') ?? $data['email'];
        try {
            Password::sendResetLink(['email' => $email]);
        } catch (\Throwable $exception) {
            // Keep the public response the same for unknown accounts and delivery failures.
            Log::warning('Password reset delivery failed.', ['exception' => $exception::class]);
        }

        return to_route('password.request')->with('status', self::MESSAGE);
    }
}
