<?php

namespace App\Services\Orders;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeletionRequest
{
    public function submit(User $user): AccountDeletionRequest
    {
        return DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $deletion = AccountDeletionRequest::firstOrCreate(['user_id' => $user->id]);
            if ($deletion->status !== 'pending') {
                $deletion->update(['status' => 'pending', 'response' => null, 'reviewed_by' => null, 'reviewed_at' => null]);
            }

            return $deletion;
        }, 3);
    }
}
