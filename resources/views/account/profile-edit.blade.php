@extends($user->role === \App\Enums\UserRole::Buyer ? 'layouts.app' : 'layouts.portal')

@section('title', 'Edit profile · FarSell')

@section('content')
    <div class="mx-auto max-w-2xl space-y-5">
        <a href="{{ route('account.profile') }}" class="text-sm text-accent underline">Back to My Profile</a>
        <h1 class="text-xl font-semibold">Edit profile &amp; security</h1>

        <section class="fs-card p-5" aria-labelledby="edit-details">
            <h2 id="edit-details" class="font-semibold">Account details</h2>
            <p class="mt-1 text-sm text-text-muted">Update your name, sign-in email, and contact number. This does not change saved delivery addresses or past orders.</p>
            <form method="post" action="{{ route('account.profile.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')
                @if($errors->profile->any())
                    <ul role="alert" class="list-disc pl-5 text-sm text-red-600">
                        @foreach($errors->profile->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                @endif
                <label class="block text-sm" for="profile-name">Name
                    <input id="profile-name" name="name" type="text" autocomplete="name" maxlength="120" required value="{{ old('name', $user->name) }}" class="mt-1 w-full rounded-lg">
                </label>
                <label class="block text-sm" for="profile-email">Email
                    <input id="profile-email" name="email" type="email" autocomplete="email" maxlength="180" required value="{{ old('email', $user->email) }}" class="mt-1 w-full rounded-lg">
                </label>
                <label class="block text-sm" for="profile-phone">Phone (optional)
                    <input id="profile-phone" name="phone" type="tel" autocomplete="tel" maxlength="30" value="{{ old('phone', $user->phone) }}" placeholder="e.g. +63 917 123 4567" class="mt-1 w-full rounded-lg">
                </label>
                <label class="block text-sm" for="profile-current-password">Current password
                    <input id="profile-current-password" name="current_password" type="password" autocomplete="current-password" required class="mt-1 w-full rounded-lg">
                </label>
                <p class="text-xs text-text-muted">Your current password is required to save changes. Roles and rider approvals cannot be edited here.</p>
                <button class="btn-accent">Save profile</button>
            </form>
        </section>

        <section class="fs-card p-5" aria-labelledby="change-password">
            <h2 id="change-password" class="font-semibold">Change password</h2>
            <p class="mt-1 text-sm text-text-muted">You will be signed out after changing your password. Other sessions will be invalidated; your current session cart will be cleared.</p>
            <form method="post" action="{{ route('account.profile.password') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')
                @if($errors->password->any())
                    <ul role="alert" class="list-disc pl-5 text-sm text-red-600">
                        @foreach($errors->password->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                @endif
                <label class="block text-sm" for="password-current">Current password
                    <input id="password-current" name="current_password" type="password" autocomplete="current-password" required class="mt-1 w-full rounded-lg">
                </label>
                <label class="block text-sm" for="password-new">New password
                    <input id="password-new" name="password" type="password" autocomplete="new-password" minlength="8" required class="mt-1 w-full rounded-lg">
                </label>
                <label class="block text-sm" for="password-confirm">Confirm new password
                    <input id="password-confirm" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required class="mt-1 w-full rounded-lg">
                </label>
                <p class="text-xs text-text-muted">Use at least 8 characters and a different password from your current one.</p>
                <button class="btn-accent">Change password</button>
            </form>
        </section>

        <section class="fs-card border-red-500 p-5" aria-labelledby="delete-account">
            <h2 id="delete-account" class="font-semibold">Delete account</h2>
            @if($errors->deletion->any())
                <ul role="alert" class="mt-3 list-disc pl-5 text-sm text-red-600">
                    @foreach($errors->deletion->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif
            @if($deletionBlocked)
                <p class="mt-2 text-sm text-text-muted">Self-deletion is unavailable for admin, seller, or rider accounts, or accounts with orders, a shop, or seller/rider applications. Contact an administrator for help preserving marketplace records.</p>
            @else
                <p class="mt-2 text-sm text-text-muted">Permanently deletes your account and saved addresses and signs you out. This cannot be undone. Only buyer accounts without orders, shops, or seller/rider applications can self-delete.</p>
                <form method="post" action="{{ route('account.profile.destroy') }}" class="mt-4 space-y-4">
                    @csrf
                    @method('DELETE')
                    <label class="block text-sm" for="delete-password">Current password
                        <input id="delete-password" name="current_password" type="password" autocomplete="current-password" required class="mt-1 w-full rounded-lg">
                    </label>
                    <label class="block text-sm" for="delete-confirm">Type DELETE to confirm
                        <input id="delete-confirm" name="confirmation" type="text" autocomplete="off" pattern="DELETE" required class="mt-1 w-full rounded-lg">
                    </label>
                    <button class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Permanently delete my account</button>
                </form>
            @endif
        </section>
    </div>
@endsection
