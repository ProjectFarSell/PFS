<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Services\Orders\ReturnRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ReturnRequestController extends Controller
{
    private function visible(Request $request)
    {
        $query = ReturnRequest::query();

        return match ($request->user()->role) {
            UserRole::Buyer => $query->whereHas('item.order', fn ($q) => $q->where('user_id', $request->user()->id)),
            UserRole::Seller => $query->whereHas('item.fulfillment.shop', fn ($q) => $q->where('user_id', $request->user()->id)),
            UserRole::Admin => $query,
            default => abort(403),
        };
    }

    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(ReturnRequest::STATUSES)]]);
        $cases = $this->visible($request)->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->with(['item.order:id,number', 'events'])->latest('id')->paginate(15)->withQueryString();

        return view('returns.index', compact('cases'));
    }

    public function store(Request $request, OrderItem $item, ReturnRequestService $service)
    {
        abort_unless($item->order->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(['damaged', 'incorrect', 'missing', 'other'])],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
            'evidence' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        $path = $request->file('evidence')?->store('return-evidence', 'local');
        try {
            $service->submit($item, $request->user(), [...$data, 'evidence_path' => $path]);
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return to_route('returns.index')->with('status', 'Return/refund request submitted.');
    }

    public function review(Request $request, ReturnRequest $returnRequest, ReturnRequestService $service)
    {
        $this->visible($request)->findOrFail($returnRequest->id);
        $data = $request->validate([
            'action' => ['required', Rule::in(['seller_approved', 'seller_declined', 'approved', 'rejected', 'refunded'])],
            'note' => ['required', 'string', 'min:5', 'max:2000'],
            'approved_amount' => ['required_if:action,approved', 'nullable', 'numeric', 'decimal:0,2', 'min:0.01'],
            'return_required' => ['nullable', 'boolean'],
            'return_received' => ['nullable', 'boolean'],
            'refund_reference' => ['required_if:action,refunded', 'nullable', 'string', 'min:5', 'max:255'],
            'reimbursement_paid' => ['accepted_if:action,refunded'],
        ]);
        $service->review($returnRequest, $request->user(), $data);

        return back()->with('status', 'Return/refund request updated.');
    }

    public function evidence(Request $request, ReturnRequest $returnRequest)
    {
        $case = $this->visible($request)->findOrFail($returnRequest->id);
        abort_unless($case->evidence_path && str_starts_with($case->evidence_path, 'return-evidence/') && Storage::disk('local')->exists($case->evidence_path), 404);

        return Storage::disk('local')->response($case->evidence_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
