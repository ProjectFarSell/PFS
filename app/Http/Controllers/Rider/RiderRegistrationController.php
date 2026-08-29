<?php

namespace App\Http\Controllers\Rider;

use App\Enums\RiderStatus;
use App\Http\Controllers\Controller;
use App\Models\RiderDocument;
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
            'license_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'id_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'vehicle_reg_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $user = $request->user();

        // Role is NOT changed here. A submitted application only creates/updates
        // the RiderProfile with status Pending. The user's role only becomes
        // Rider once an admin actually approves the application.
        $profile = $user->riderProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'vehicle_type' => $data['vehicle_type'],
                'plate_number' => $data['plate_number'] ?? null,
                'license_no' => $data['license_no'],
                'city' => $data['city'],
                'bio' => $data['bio'] ?? null,
                'status' => RiderStatus::Pending,
                'reviewed_at' => null,
            ]
        );

        // Each uploaded document becomes its own unverified RiderDocument row.
        // Re-uploading on a fresh application just adds new rows; verification
        // of specific documents is a separate admin-side action.
        $uploads = [
            'license_document' => 'license',
            'id_document' => 'id',
            'vehicle_reg_document' => 'vehicle_reg',
        ];

        foreach ($uploads as $field => $documentType) {
            if ($request->hasFile($field)) {
                $path = $request->file($field)->store('rider-documents', 'local');

                $profile->documents()->create([
                    'document_type' => $documentType,
                    'file_path' => $path,
                    'verified' => false,
                ]);
            }
        }

        return redirect()->route('rider.profile')->with('status', 'Application submitted. We will review it shortly.');
    }

    public function profile(): View
    {
        $profile = auth()->user()->riderProfile;

        abort_unless($profile, 404);

        return view('rider.profile', compact('profile'));
    }
}
