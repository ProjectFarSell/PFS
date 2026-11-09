@extends('layouts.auth')
@section('title', 'Seller dashboard demo · FarSell')

@section('content')
    @include('demo.dashboard', ['panel' => 'seller', 'heading' => 'Seller dashboard'])
@endsection
