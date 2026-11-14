<?php

namespace App\Http\Requests\Api;

use App\Services\Orders\DeliveryExceptionService;
use App\Support\PrivacyNotice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Pagination\Cursor;
use Illuminate\Validation\Rules\Password;

class MobileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('cursor') && is_string($this->input('cursor')) && ! Cursor::fromEncoded($this->input('cursor'))) {
                $validator->errors()->add('cursor', 'Use the cursor returned by the previous page.');
            }
        });
    }

    public function rules(): array
    {
        $id = ['required', 'uuid'];
        $proof = ['channel' => ['required', 'in:email,phone'], 'code' => ['required_if:channel,email', 'prohibited_unless:channel,email', 'digits:6'], 'id_token' => ['required_if:channel,phone', 'prohibited_unless:channel,phone', 'string', 'max:10000']];
        $rules = match ($this->route()->getName()) {
            'api.mobile.login' => ['email' => ['required', 'email', 'max:180'], 'password' => ['required', 'string', 'max:1024'], 'app' => ['required', 'in:buyer,rider']],
            'api.mobile.register' => ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:180', 'unique:users,email'], 'password' => ['required', 'confirmed', 'max:1024', Password::min(12)->mixedCase()->numbers()->symbols()], 'privacy_consent' => ['required', 'accepted'], 'privacy_notice_version' => ['required', 'in:'.PrivacyNotice::VERSION], 'role' => ['prohibited'], 'is_demo_account' => ['prohibited']],
            'api.mobile.challenge.send' => ['challenge_token' => ['required', 'string', 'max:512'], 'channel' => ['required', 'in:email,phone']],
            'api.mobile.challenge.verify' => ['challenge_token' => ['required', 'string', 'max:512'], ...$proof],
            'api.mobile.reauthenticate' => ['password' => ['required_without:id_token', 'prohibits:id_token', 'string', 'max:1024'], 'id_token' => ['required_without:password', 'prohibits:password', 'string', 'max:16384']],
            'api.mobile.profile.update' => [
                'name' => ['sometimes', 'required', 'string', 'max:120'],
                'bio' => ['sometimes', 'nullable', 'string', 'max:280'],
                'gender' => ['sometimes', 'nullable', 'in:male,female,non_binary,prefer_not_to_say'],
                'birthday' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
                'email' => ['sometimes', 'required', 'email', 'max:180'],
                'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s().-]{5,29}$/'],
                'reauth_token' => ['sometimes', 'string', 'max:512'],
                'role' => ['prohibited'], 'id' => ['prohibited'], 'google_id' => ['prohibited'],
                'email_verified' => ['prohibited'], 'phone_verified' => ['prohibited'],
            ],
            'api.mobile.profile.avatar.store' => ['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096']],
            'api.mobile.contact.send' => ['channel' => ['required', 'in:email,phone']],
            'api.mobile.contact.verify' => $proof,
            'api.mobile.deletion' => ['confirmation' => ['required', 'in:DELETE'], 'reauth_token' => ['required', 'string', 'max:512']],
            'api.mobile.devices.put' => ['installation_id' => $id, 'platform' => ['required', 'in:android,ios'], 'app' => ['required', 'in:buyer,rider'], 'fcm_token' => ['required', 'string', 'max:4096'], 'enabled_events' => ['required', 'array', 'max:8'], 'enabled_events.*' => ['required', 'distinct', 'in:order.placed,order.status_changed,shipment.status_changed,offer.created,offer.expired,offer.taken,shipment.assigned,shipment.cancelled,chat.message_created']],
            'api.mobile.cart.add' => ['product_id' => $id, 'variant_id' => ['nullable', 'uuid'], 'qty' => ['required', 'integer', 'min:1', 'max:99'], 'cart_version' => ['required', 'integer', 'min:0']],
            'api.mobile.cart.quantity' => ['qty' => ['required', 'integer', 'min:0', 'max:99'], 'cart_version' => ['required', 'integer', 'min:0']],
            'api.mobile.cart.merge' => ['merge_id' => $id, 'cart_version' => ['required', 'integer', 'min:0'], 'lines' => ['required', 'array', 'max:100'], 'lines.*.product_id' => $id, 'lines.*.variant_id' => ['nullable', 'uuid'], 'lines.*.qty' => ['required', 'integer', 'min:1', 'max:99']],
            'api.mobile.addresses.store', 'api.mobile.addresses.update' => ['label' => ['nullable', 'string', 'max:60'], 'line1' => ['required', 'string', 'max:255'], 'region_id' => $id, 'province_id' => $id, 'city_id' => $id, 'barangay_id' => $id, 'postal_code' => ['nullable', 'string', 'max:20'], 'phone' => ['nullable', 'string', 'max:40'], 'is_default' => ['required', 'boolean']],
            'api.mobile.quotes' => ['address_id' => $id, 'cart_version' => ['required', 'integer', 'min:0']],
            'api.mobile.orders.store' => ['quote_id' => $id, 'cart_version' => ['required', 'integer', 'min:0'], 'payment_method' => ['required', 'in:cod,gateway_stub']],
            'api.mobile.rider.availability' => ['is_available' => ['required', 'boolean']],
            'api.mobile.rider.decision' => ['decision' => ['required', 'in:accept,decline']],
            'api.mobile.rider.deliver' => ['delivery_recipient_name' => ['required', 'string', 'max:120'], 'delivery_pin' => ['required', 'digits:6'], 'delivery_note' => ['required', 'string', 'max:1000'], 'cod_collected' => ['sometimes', 'boolean'], 'delivery_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']],
            'api.mobile.rider.fail' => ['failure_reason' => ['required', 'in:'.implode(',', DeliveryExceptionService::REASONS)], 'contact_method' => ['required', 'in:phone,chat,doorstep'], 'exception_note' => ['required', 'string', 'min:10', 'max:1000']],
            'api.mobile.broadcast' => ['socket_id' => ['required', 'regex:/^[0-9]+\.[0-9]+$/'], 'channel_name' => ['required', 'string', 'max:240']],
            'api.mobile.products' => ['q' => ['nullable', 'string', 'max:120'], 'category_id' => ['nullable', 'uuid'], 'shop_id' => ['nullable', 'uuid'], 'city_id' => ['nullable', 'uuid'], 'price_min' => ['nullable', 'integer', 'min:0'], 'price_max' => ['nullable', 'integer', 'min:0', ...($this->filled('price_min') ? ['gte:price_min'] : [])], 'sort' => ['nullable', 'in:newest,price_asc,price_desc']],
            default => [],
        };

        return [...$rules, 'limit' => ['sometimes', 'integer', 'min:1', 'max:50'], 'cursor' => ['nullable', 'string', 'max:512']];
    }
}
