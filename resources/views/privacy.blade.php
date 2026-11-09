@extends('layouts.auth')
@section('title', 'Privacy notice · FarSell')
@section('content')
<div class="mx-auto max-w-3xl space-y-6 px-4 py-10">
    <div class="flex items-center justify-between"><a href="{{ auth()->check() ? route('account.profile.edit') : route('register') }}" class="text-accent underline">{{ auth()->check() ? 'Back to profile settings' : 'Back to create account' }}</a><x-theme-toggle /></div>
    <header><p class="section-eyebrow">FarSell marketplace</p><h1 class="mt-2 text-3xl font-bold">Privacy notice</h1><p class="mt-2 text-sm text-text-muted">Version {{ \App\Support\PrivacyNotice::VERSION }}</p></header>
    <section class="fs-card p-5"><h2 class="text-lg font-semibold">What we collect and why</h2>
        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm">
            <li><strong>Account:</strong> name, email, password hash, chosen role intent, and privacy acceptance date/version to create and protect your account. Signup does not require a home address, ID, or phone number.</li>
            <li><strong>Verification and profile:</strong> a phone number only if you add one or choose phone verification; verification status and optional profile photo.</li>
            <li><strong>Orders:</strong> delivery name, contact number, address, purchased items, prices, and delivery/payment status to fulfill purchases and keep transaction records.</li>
            <li><strong>Sellers and riders:</strong> application, shop/pickup, vehicle, and required supporting-document information to review eligibility and arrange deliveries. Ordinary buyers do not need these documents.</li>
            <li><strong>Delivery:</strong> status history, recipient name, delivery PIN, handover note, COD confirmation, and optional proof photo to verify handover. Proof photos are restricted to the buyer, assigned rider, and admin.</li>
            <li><strong>Optional content:</strong> chat messages, product/rider ratings, feedback tags, comments, and review attachments when you use those features. Published reviews and listing images can be seen publicly; avoid including private details.</li>
            <li><strong>Technical records:</strong> session cookies, sign-in/session information, and diagnostic logs support authentication and troubleshooting.</li>
        </ul>
    </section>
    <section class="fs-card p-5"><h2 class="text-lg font-semibold">Who receives information</h2><p class="mt-3 text-sm">Sellers and assigned riders receive the order and contact details needed to fulfill a delivery. Admins can review marketplace records and applications. Other users cannot edit your profile or browse your private order history.</p><p class="mt-3 text-sm">Configured service providers may process information: Google receives account information during Google sign-in; Firebase handles phone verification and reCAPTCHA; the mail provider processes email notifications; cloud storage hosts uploaded files; and Invoice Generator receives the order details needed when generating an invoice. Hosted Google Fonts may receive browser connection information when pages load. These integrations depend on the deployment configuration. Default profile initials are generated locally.</p></section>
    <section class="fs-card p-5"><h2 class="text-lg font-semibold">Your choices and account deletion</h2><p class="mt-3 text-sm">You can browse without an account. Creating an account requires the privacy acknowledgement and consent checkbox; optional reviews, attachments, and Google sign-in are your choice. Edit your name, email, and optional phone from My Profile → Edit profile &amp; security.</p><p class="mt-3 text-sm">Use the account deletion request form in profile settings to ask an administrator to review deletion and discuss retained records. All account roles can submit a request and read the admin response there. Eligible buyers without orders or applications can also delete their account immediately using their password and DELETE confirmation. Order, payment, delivery, and application history may need to be retained and reviewed; submitting a request does not erase it automatically. Contact the project administrator through this request process for privacy questions or to withdraw account-data consent by requesting deletion.</p></section>
    <section class="fs-card p-5"><h2 class="text-lg font-semibold">Storage and safeguards</h2><p class="mt-3 text-sm">Passwords are hashed. Private pages require authentication and role/ownership checks. We do not store payment-card details in these forms. Public uploads and private evidence use different access rules. Account and saved-address deletion is available where it will not remove marketplace history. Record retention depends on transaction and operational needs; there is currently no automatic deletion schedule.</p><p class="mt-3 text-sm">Use an HTTPS deployment when sharing personal data. A local address beginning with http:// does not provide encrypted network transport. This notice describes the current project features; it does not certify legal compliance.</p></section>
    @auth
        <section class="fs-card p-5"><h2 class="font-semibold">Privacy acknowledgement</h2>
        @if(auth()->user()->privacy_notice_version === \App\Support\PrivacyNotice::VERSION)
            <p class="mt-2 text-sm">Your acceptance of this version was recorded on {{ auth()->user()->privacy_accepted_at?->format('Y-m-d H:i') }}.</p>
        @else
            <form method="post" action="{{ route('account.privacy.consent') }}" class="mt-3 space-y-3">@csrf<label class="flex gap-2 text-sm"><input type="checkbox" name="privacy_consent" value="1" required class="mt-1">I have read this privacy notice and agree to the collection and use of my data for my FarSell account and requested services.</label><x-ui.button variant="primary" size="lg" type="submit">Record my acceptance</x-ui.button></form>
        @endif
        </section>
    @endauth
</div>
@endsection
