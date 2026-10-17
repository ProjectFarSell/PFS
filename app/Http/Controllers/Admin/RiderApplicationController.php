<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RiderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\RiderDocument;
use App\Models\RiderBarangayAssignment;
use App\Models\RiderProfile;
use App\Models\PsgcBarangay;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RiderApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['status' => ['nullable', Rule::enum(RiderStatus::class)]]);
        $status = $data['status'] ?? RiderStatus::Pending->value;

        return view('admin.riders.index', [
            'applications' => RiderProfile::with('user:id,name,email,role')
                ->where('status', $status)->oldest()->orderBy('id')->paginate(10)->withQueryString(),
            'selectedStatus' => $status,
            'statuses' => RiderStatus::cases(),
        ]);
    }

    public function show(RiderProfile $riderProfile): View
    {
        $riderProfile->load(['user:id,name,email,role', 'documents', 'reviewer:id,name', 'barangayAssignments.barangay.cityMunicipality']);

        return view('admin.riders.show', [
            'profile' => $riderProfile,
            'reviewToken' => $riderProfile->reviewToken(),
            'barangays' => $this->barangaysFor($riderProfile),
        ]);
    }

    public function barangayAssignments(Request $request): View
    {
        $data = $request->validate(['rider_profile_id' => ['nullable', 'integer', Rule::exists('rider_profiles', 'id')]]);
        $riders = RiderProfile::query()
            ->with('user:id,name,email,role')
            ->where('status', RiderStatus::Approved)
            ->whereHas('user', fn ($query) => $query->where('role', UserRole::Rider))
            ->orderBy('city')
            ->orderBy('id')
            ->get();
        $profile = isset($data['rider_profile_id']) ? $riders->firstWhere('id', $data['rider_profile_id']) : null;
        abort_if(isset($data['rider_profile_id']) && ! $profile, 404);
        $profile?->load('barangayAssignments.barangay.cityMunicipality');

        return view('admin.riders.barangays', [
            'riders' => $riders,
            'profile' => $profile,
            'barangays' => $profile ? $this->barangaysFor($profile) : collect(),
        ]);
    }

    public function assignBarangay(Request $request, RiderProfile $riderProfile): RedirectResponse
    {
        $data = $request->validate(['psgc_barangay_id' => ['required', 'integer', Rule::exists('psgc_barangays', 'id')]]);
        abort_unless($riderProfile->isApproved() && $riderProfile->user()->where('role', UserRole::Rider)->exists(), 422);

        $barangay = PsgcBarangay::query()->with('cityMunicipality:id,name')->findOrFail($data['psgc_barangay_id']);
        abort_unless(mb_strtolower(trim($barangay->cityMunicipality->name)) === mb_strtolower(trim($riderProfile->city)), 422);
        $existing = RiderBarangayAssignment::query()->where('psgc_barangay_id', $barangay->id)->first();
        if ($existing && $existing->rider_profile_id !== $riderProfile->id) {
            throw ValidationException::withMessages(['psgc_barangay_id' => 'This barangay is already assigned to another rider.']);
        }

        RiderBarangayAssignment::query()->firstOrCreate([
            'rider_profile_id' => $riderProfile->id,
            'psgc_barangay_id' => $barangay->id,
        ]);

        return back()->with('status', $barangay->name.' is now assigned to this rider.');
    }

    public function removeBarangay(RiderProfile $riderProfile, RiderBarangayAssignment $assignment): RedirectResponse
    {
        abort_unless($assignment->rider_profile_id === $riderProfile->id, 404);
        $assignment->delete();

        return back()->with('status', 'Barangay coverage removed.');
    }

    private function barangaysFor(RiderProfile $riderProfile)
    {
        $city = mb_strtolower(trim($riderProfile->city));

        return PsgcBarangay::query()->with('cityMunicipality:id,name')
            ->whereHas('cityMunicipality', fn ($query) => $query->whereRaw('LOWER(name) = ?', [$city]))
            ->orderBy('name')->get(['id', 'psgc_city_municipality_id', 'name']);
    }

    public function review(Request $request, RiderProfile $riderProfile): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_token' => ['required', 'string', 'size:64'],
            'confirmed' => ['accepted'],
            'review_note' => ['required_if:decision,reject', 'nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $riderProfile, $data) {
            // Same lock order as applicant edits: account, then profile.
            $user = User::whereKey($riderProfile->user_id)->lockForUpdate()->firstOrFail();
            $profile = RiderProfile::whereKey($riderProfile->id)->lockForUpdate()->firstOrFail();

            if ($profile->status !== RiderStatus::Pending || ! hash_equals($profile->reviewToken(), $data['review_token'])) {
                throw ValidationException::withMessages(['decision' => 'This application changed or was already reviewed. Reload the page before reviewing it.']);
            }

            if ($data['decision'] === 'approve') {
                if (! in_array($user->role, [UserRole::Buyer, UserRole::Rider], true)) {
                    throw ValidationException::withMessages(['decision' => 'A seller or admin account cannot be converted into a rider here. Use a separate rider account.']);
                }
                $user->update(['role' => UserRole::Rider]);
            }

            $profile->update([
                'status' => $data['decision'] === 'approve' ? RiderStatus::Approved : RiderStatus::Rejected,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
                'review_note' => $data['review_note'] ?? null,
            ]);
        });

        return to_route('admin.riders.show', $riderProfile)
            ->with('status', $data['decision'] === 'approve' ? 'Application approved. Rider access is now enabled.' : 'Application rejected. The applicant can update and resubmit.');
    }

    public function document(RiderProfile $riderProfile, RiderDocument $document): StreamedResponse
    {
        abort_unless($document->rider_profile_id === $riderProfile->id, 404);
        // Only files from the private rider upload directory may be downloaded.
        abort_unless(str_starts_with($document->file_path, 'rider-documents/')
            && ! str_contains($document->file_path, '..')
            && ! str_contains($document->file_path, '\\'), 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, basename($document->file_path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
