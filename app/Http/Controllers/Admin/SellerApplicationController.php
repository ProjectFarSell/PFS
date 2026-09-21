<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SellerApplication;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SellerApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])]]);
        $status = $data['status'] ?? 'pending';

        return view('admin.sellers.index', [
            'selectedStatus' => $status,
            'applications' => SellerApplication::with('user:id,name,email')->where('status', $status)
                ->orderBy('submitted_at')->orderBy('id')->paginate(10)->withQueryString(),
        ]);
    }

    public function show(SellerApplication $application): View
    {
        $application->load(['user', 'reviewer:id,name']);

        return view('admin.sellers.show', compact('application'));
    }

    public function review(Request $request, SellerApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'revision' => ['required', 'uuid'],
            'confirmed' => ['accepted'],
            'review_note' => ['required_if:decision,reject', 'nullable', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($request, $application, $data) {
            $user = User::whereKey($application->user_id)->lockForUpdate()->firstOrFail();
            $application = SellerApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($application->status !== 'pending' || $application->revision !== $data['revision']) {
                throw ValidationException::withMessages(['decision' => 'Application changed or was already reviewed. Reload before reviewing.']);
            }
            if ($data['decision'] === 'approve') {
                if (! in_array($user->role, [UserRole::Buyer, UserRole::Seller], true) || $user->shop()->exists()) {
                    throw ValidationException::withMessages(['decision' => 'This account has an incompatible role or already owns a shop. No changes were made.']);
                }
                $user->shop()->create([
                    'name' => $application->shop_name, 'city' => $application->city,
                    'tagline' => $application->tagline, 'is_active' => true,
                    'slug' => Str::slug($application->shop_name).'-'.Str::uuid(),
                ]);
                $user->update(['role' => UserRole::Seller]);
            }
            $application->update([
                'status' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
                'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['review_note'] ?? null,
            ]);
        });

        return to_route('admin.sellers.show', $application)->with('status', $data['decision'] === 'approve'
            ? 'Seller approved. Shop created and product listing enabled.' : 'Application rejected. The applicant can update and resubmit.');
    }
}
