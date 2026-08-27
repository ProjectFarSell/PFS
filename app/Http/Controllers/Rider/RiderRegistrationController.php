<?php

namespace App\Http\Controllers\Rider;

use App\Enums\RiderStatus;
use App\Http\Controllers\Controller;
use App\Models\RiderProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiderRegistrationController extends Controller
{
    public function create(): View
    {
        $profile = auth()->user()->riderProfile;

        return view('rider.register', compact('profile'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vehicle_type' => ['required', 'in:motorcycle,bicycle,car,van'],
            'plate_number' => ['nullable', 'string', 'max:20'],
            'license_no' => ['required', 'string', 'max:40'],
            'city' => ['required', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        // Role is NOT changed here. A submitted application only creates/updates
        // the RiderProfile with status Pending. The user's role only becomes
        // Rider once an admin actually approves the application (see admin
        // approval action, which should set role => Rider alongside status => Approved).
        $user->riderProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                ...$data,
                'status' => RiderStatus::Pending,
                'reviewed_at' => null,
            ]
        );

        return redirect()->route('rider.profile')->with('status', 'Application submitted. We will review it shortly.');
    }

    public function profile(): View
    {
        $profile = auth()->user()->riderProfile;

        abort_unless($profile, 404);

        return view('rider.profile', compact('profile'));
    }
}
