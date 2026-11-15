<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Orders\MarketplaceSettings;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MarketplaceSettingsController extends Controller
{
    public function edit(MarketplaceSettings $service)
    {
        return view('admin.marketplace-settings', ['settings' => $service->current()]);
    }

    public function update(Request $request, MarketplaceSettings $service)
    {
        $data = $request->validate(['commission_percent' => ['required', 'string', 'regex:/^\d{1,3}(?:\.\d{1,2})?$/D'],
            'delivery_fee' => ['required', 'string', 'regex:/^\d{1,4}(?:\.\d{1,2})?$/D'],
            'payout_hold_days' => ['required', 'integer', 'between:0,90'], 'version' => ['required', 'integer', 'min:1']]);
        try {
            $service->update($request->user(), ['commission_bps' => Money::cents($data['commission_percent']),
                'delivery_fee_cents' => Money::cents($data['delivery_fee']), 'payout_hold_days' => $data['payout_hold_days'], 'version' => $data['version']]);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $errors[match ($key) {
                    'commission_bps' => 'commission_percent', 'delivery_fee_cents' => 'delivery_fee', default => $key
                }] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }

        return to_route('admin.marketplace-settings.edit')->with('status', 'Marketplace settings saved. Existing orders keep their original fees.');
    }
}
