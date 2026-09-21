<?php

namespace App\Http\Controllers\Seller;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);
        if ($user->role === UserRole::Seller && $user->shop()->exists()) {
            return to_route('seller.dashboard');
        }

        return view('seller.application', ['application' => $user->sellerApplication]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, [UserRole::Buyer, UserRole::Seller], true), 403);
        $data = $request->validate([
            'shop_name' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $data) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);
            $application = $user->sellerApplication()->lockForUpdate()->first();
            if ($user->shop()->exists() || $application?->status === 'approved') {
                throw ValidationException::withMessages(['application' => 'Your shop is already set up or approved. Contact an administrator for changes.']);
            }
            $user->sellerApplication()->updateOrCreate([], [
                ...$data, 'status' => 'pending', 'revision' => (string) Str::uuid(), 'submitted_at' => now(),
                'reviewed_at' => null, 'reviewed_by' => null, 'review_note' => null,
            ]);
        });

        return to_route('seller.apply')->with('status', 'Seller application submitted. An administrator will review it before your shop is enabled.');
    }
}
