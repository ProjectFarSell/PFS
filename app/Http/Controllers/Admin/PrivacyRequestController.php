<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrivacyRequestController extends Controller
{
    public function index(): View
    {
        return view('admin.privacy-requests.index', [
            'requests' => AccountDeletionRequest::with('user', 'reviewer')->orderByRaw("case when status = 'pending' then 0 else 1 end")->latest('updated_at')->paginate(20),
        ]);
    }

    public function review(Request $request, AccountDeletionRequest $deletionRequest): RedirectResponse
    {
        $data = $request->validate(['response' => ['required', 'string', 'max:1000']]);
        // Reviewing a request must never silently delete an account or order history.
        AccountDeletionRequest::whereKey($deletionRequest->id)->where('status', 'pending')->update([
            'status' => 'reviewed', 'response' => $data['response'],
            'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
        ]);

        return to_route('admin.privacy-requests.index')->with('status', 'Review response saved. The account holder can read it in profile settings.');
    }
}
