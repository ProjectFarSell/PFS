<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RiderStatus;
use App\Http\Controllers\Controller;
use App\Models\RiderProfile;
use App\Models\SellerApplication;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PartnerController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'tab' => ['nullable', Rule::in(['riders', 'sellers'])],
            'status' => ['nullable', 'string', 'max:20'],
        ]);
        $tab = $data['tab'] ?? 'riders';
        $status = $data['status'] ?? null;

        if ($tab === 'riders') {
            $statuses = RiderStatus::cases();
            abort_if($status && ! in_array($status, array_column($statuses, 'value'), true), 422);
            $records = RiderProfile::query()->with('user:id,name,email,role')
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest('id')->paginate(12)->withQueryString();
        } else {
            $statuses = collect(['pending', 'approved', 'rejected']);
            abort_if($status && ! $statuses->contains($status), 422);
            $records = SellerApplication::query()->with('user:id,name,email')
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest('submitted_at')->latest('id')->paginate(12)->withQueryString();
        }

        return view('admin.partners.index', compact('tab', 'status', 'statuses', 'records'));
    }
}
