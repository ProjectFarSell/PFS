@extends('layouts.app')

@section('title', 'Edit address · FarSell')

@section('content')
    <div class="max-w-lg mx-auto rounded-2xl bg-white border border-stone-200 p-5">
        <h1 class="text-lg font-semibold mb-3">Edit address</h1>
        @include('account.addresses._form')
    </div>
@endsection
