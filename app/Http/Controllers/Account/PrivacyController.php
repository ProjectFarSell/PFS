<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Support\PrivacyNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        DB::transaction(function () use ($request): void {
            // Serialize requests for this account, including the first submission.
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $deletion = AccountDeletionRequest::firstOrCreate(['user_id' => $request->user()->id]);
            if ($deletion->status !== 'pending') {
                $deletion->update(['status' => 'pending', 'response' => null, 'reviewed_by' => null, 'reviewed_at' => null]);
            }
        });

        return to_route('account.profile.edit')->with('status', 'Account deletion request submitted for admin review. Your account has not been deleted yet.');
    }
}
