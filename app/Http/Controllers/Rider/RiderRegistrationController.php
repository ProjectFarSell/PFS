<?php

namespace App\Http\Controllers\Rider;

use App\Enums\RiderStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RiderRegistrationController extends Controller
{
    public function create(): View|RedirectResponse
    {
        $profile = auth()->user()->riderProfile;

        if ($profile && in_array($profile->status, [RiderStatus::Approved, RiderStatus::Suspended], true)) {
            return to_route('rider.profile')->with('status', 'Contact an administrator to change an approved or suspended application.');
        }

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

        DB::transaction(function () use ($user, $request, $data) {
            // Serialize submissions and admin decisions on the same account.
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = $user->riderProfile()->lockForUpdate()->first();
            if ($existing && in_array($existing->status, [RiderStatus::Approved, RiderStatus::Suspended], true)) {
                throw ValidationException::withMessages(['application' => 'An approved or suspended application cannot be resubmitted. Contact an administrator.']);
            }

            // Submission never grants a role; only an admin approval does.
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
                    'reviewed_by' => null,
                    'review_note' => null,
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
        });

        return redirect()->route('rider.profile')->with('status', 'Application submitted. We will review it shortly.');
    }

    public function profile(): View
    {
        $profile = auth()->user()->riderProfile;

        abort_unless($profile, 404);

        return view('rider.profile', compact('profile'));
    }
}
