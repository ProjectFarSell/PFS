<fieldset class="space-y-2 rounded-lg border border-surface-border p-3">
    <legend class="text-sm font-medium">Vehicle upgrade (optional)</legend>
    <label class="block text-sm">Requested vehicle<select name="requested_vehicle_tier" class="mt-1 w-full rounded-lg"><option value="">Keep current requirement</option>@foreach(\App\Enums\ShippingVehicleTier::cases() as $tier)<option value="{{ $tier->value }}">{{ $tier->label() }}</option>@endforeach</select></label>
    <label class="block text-sm">Reason for upgrade<textarea name="vehicle_upgrade_reason" minlength="10" maxlength="1000" class="mt-1 w-full rounded-lg" placeholder="Explain why the packed order needs a larger vehicle."></textarea></label>
    <p class="text-xs text-text-muted">Requests pause internal dispatch until Admin review. Shipping fees do not change.</p>
</fieldset>
