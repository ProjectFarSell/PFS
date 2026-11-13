<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Orders\DeletionRequest;
use App\Support\PrivacyNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrivacyController extends Controller
{
    public function consent(Request $request): RedirectResponse
    {
        $request->validate(['privacy_consent' => ['required', 'accepted']]);
        $request->user()->forceFill([
            'privacy_accepted_at' => now(),
            'privacy_notice_version' => PrivacyNotice::VERSION,
        ])->save();

        return to_route('account.profile.edit')->with('status', 'Privacy notice acceptance recorded.');
    }

    public function requestDeletion(Request $request): RedirectResponse
    {
        $request->validateWithBag('privacy', ['confirmation' => ['required', 'in:DELETE']]);

        app(DeletionRequest::class)->submit($request->user());

        return to_route('account.profile.edit')->with('status', 'Account deletion request submitted for admin review. Your account has not been deleted yet.');
    }
}
