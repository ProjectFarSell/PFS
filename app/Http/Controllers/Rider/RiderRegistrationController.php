<?php

namespace App\Http\Controllers\Rider;

use App\Enums\RiderStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        $profile?->load('documents');

        return view('rider.register', compact('profile'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s().-]{5,29}$/'],
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
        $replacedPaths = [];

        DB::transaction(function () use ($user, $request, $data, &$replacedPaths) {
            // Serialize submissions and admin decisions on the same account.
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = $user->riderProfile()->lockForUpdate()->first();
            if ($existing && in_array($existing->status, [RiderStatus::Approved, RiderStatus::Suspended], true)) {
                throw ValidationException::withMessages(['application' => 'An approved or suspended application cannot be resubmitted. Contact an administrator.']);
            }

            if ($request->has('phone')) {
                $user->update(['phone' => $data['phone'] ?? null]);
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

            // Unchanged document types remain on file. A new upload replaces only
            // that type and must be reviewed again.
            $uploads = [
                'license_document' => 'license',
                'id_document' => 'id',
                'vehicle_reg_document' => 'vehicle_reg',
            ];

            foreach ($uploads as $field => $documentType) {
                if ($request->hasFile($field)) {
                    $path = $request->file($field)->store('rider-documents', 'local');
                    $document = $profile->documents()
                        ->where('document_type', $documentType)
                        ->latest('id')
                        ->first();

                    if ($document) {
                        if ($document->file_path !== $path) {
                            $replacedPaths[] = $document->file_path;
                        }
                        $document->update(['file_path' => $path, 'verified' => false]);
                    } else {
                        $profile->documents()->create([
                            'document_type' => $documentType,
                            'file_path' => $path,
                            'verified' => false,
                        ]);
                    }
                }
            }
        });

        foreach (array_unique($replacedPaths) as $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return redirect()->route('rider.profile')->with('status', 'Application saved. Existing documents were kept unless you uploaded a replacement.');
    }

    public function profile(): View
    {
        $profile = auth()->user()->riderProfile;

        abort_unless($profile, 404);

        return view('rider.profile', compact('profile'));
    }
}
