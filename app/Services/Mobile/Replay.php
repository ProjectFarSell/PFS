<?php

namespace App\Services\Mobile;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Replay
{
    public function run(Request $request, array $payload, callable $work, ?string $key = null, ?string $scope = null): JsonResponse
    {
        $key ??= $request->header('Idempotency-Key');
        if (! is_string($key) || ! Str::isUuid($key)) {
            throw ValidationException::withMessages(['Idempotency-Key' => 'Supply a UUID idempotency key.']);
        }
        $scope = hash('sha256', $scope ?? $request->method().'|'.$request->route()->getName().'|'.json_encode($request->route()->parameters()));
        $hash = hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($request, $key, $scope, $hash, $work) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('mobile_receipts')->where(['user_id' => $request->user()->id, 'scope' => $scope, 'request_key' => $key])->first();
            if ($existing) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new ApiFailure('idempotency_conflict', 'This key was already used for a different request.');
                }

                return response()->json(json_decode(Crypt::decryptString($existing->result), false, flags: JSON_THROW_ON_ERROR), $existing->status)->header('Cache-Control', 'private, no-store');
            }
            $response = $work();
            DB::table('mobile_receipts')->insert(['user_id' => $request->user()->id, 'scope' => $scope, 'request_key' => $key, 'payload_hash' => $hash, 'status' => $response->getStatusCode(), 'result' => Crypt::encryptString($response->getContent()), 'created_at' => now()]);

            return $response;
        });
    }

    private function canonical(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return ['sha256' => hash_file('sha256', $value->getRealPath()), 'mime' => $value->getMimeType()];
        }
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(fn ($item) => $this->canonical($item), $value);
        }

        return $value;
    }
}
