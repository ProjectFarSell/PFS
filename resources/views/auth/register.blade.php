@extends('layouts.app')

@section('title', 'Create account · FarSell')

@section('content')
    <div class="max-w-sm mx-auto rounded-2xl bg-white border border-stone-200 p-5">
        <h1 class="text-lg font-semibold">Join FarSell</h1>
        <form method="post" action="{{ route('register') }}" class="mt-4 space-y-3">
            @csrf
            <input name="name" value="{{ old('name') }}" required placeholder="Name" class="w-full rounded-lg border-stone-200 text-sm">
            <input type="email" name="email" value="{{ old('email') }}" required placeholder="Email" class="w-full rounded-lg border-stone-200 text-sm">
            <input type="password" name="password" required placeholder="Password" class="w-full rounded-lg border-stone-200 text-sm">
            <input type="password" name="password_confirmation" required placeholder="Confirm password" class="w-full rounded-lg border-stone-200 text-sm">
            <select name="intent" class="w-full rounded-lg border-stone-200 text-sm">
                <option value="buyer">I want to shop</option>
                <option value="seller">I want to sell</option>
                <option value="rider" @selected(request('intent') === 'rider')>I want to deliver</option>
            </select>
            @if ($errors->any())
                <ul class="text-sm text-red-600 list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <button class="w-full rounded-full bg-orange-500 text-white text-sm py-2.5">Create account</button>
        </form>
        <p class="text-sm text-center mt-4">Already have an account? <a class="text-orange-600" href="{{ route('login') }}">Log in</a></p>
    </div>
@endsection
