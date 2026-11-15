<?php

namespace App\Http\Controllers\Seller;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SellerApplication;
use App\Models\SellerDocument;
use App\Models\User;
use App\Services\Security\AuditTrail;
use App\Services\Security\SafeImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['business_permit' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120']]);
        $application = $request->user()->sellerApplication()->firstOrFail();
        $file = $data['business_permit'];
        if ($file->getMimeType() === 'application/pdf') {
            if (file_get_contents($file->getRealPath(), false, null, 0, 5) !== '%PDF-') {
                throw ValidationException::withMessages(['business_permit' => 'Upload a valid PDF or image document.']);
            }
            $path = $file->store('seller-documents', 'local');
            $mime = 'application/pdf';
        } else {
            $path = app(SafeImage::class)->store($file, 'seller-documents', 'local', 'business_permit', 5242880);
            $mime = Storage::disk('local')->mimeType($path);
        }
        if (! $path) {
            throw ValidationException::withMessages(['business_permit' => 'The permit could not be saved.']);
        }
        try {
            DB::transaction(function () use ($request, $application, $path, $mime) {
                $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true) && ! $user->suspended_at, 403);
                $row = SellerApplication::whereKey($application->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
                if ($row->status === 'approved') {
                    throw ValidationException::withMessages(['business_permit' => 'Contact an administrator to update an approved application document.']);
                }
                $previous = $row->documents()->where('kind', 'business_permit')->first();
                $oldPath = $previous?->file_path;
                $document = $row->documents()->updateOrCreate(['kind' => 'business_permit'], ['file_path' => $path, 'mime_type' => $mime, 'size_bytes' => Storage::disk('local')->size($path)]);
                $row->forceFill(['status' => 'pending', 'revision' => (string) Str::uuid(), 'submitted_at' => now(), 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null])->save();
                app(AuditTrail::class)->record('seller_document.uploaded', $document, ['kind' => 'business_permit']);
                if ($oldPath && str_starts_with($oldPath, 'seller-documents/')) {
                    DB::afterCommit(function () use ($oldPath) {
                        try {
                            Storage::disk('local')->delete($oldPath);
                        } catch (\Throwable) { /* The committed private document remains valid. */
                        }
                    });
                }
            }, 3);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return to_route('seller.apply')->with('status', 'Private business permit uploaded for review.');
    }

    public function own(Request $request, SellerDocument $document)
    {
        abort_unless($document->application()->where('user_id', $request->user()->id)->exists(), 404);

        return $this->download($document);
    }

    public function admin(SellerApplication $application, SellerDocument $document)
    {
        abort_unless($document->seller_application_id === $application->id, 404);

        return $this->download($document);
    }

    private function download(SellerDocument $document)
    {
        abort_unless(str_starts_with($document->file_path, 'seller-documents/') && ! str_contains($document->file_path, '..') && ! str_contains($document->file_path, '\\') && Storage::disk('local')->exists($document->file_path), 404);
        $extension = match ($document->mime_type) {
            'application/pdf' => 'pdf', 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg'
        };

        return Storage::disk('local')->download($document->file_path, 'business-permit.'.$extension, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
