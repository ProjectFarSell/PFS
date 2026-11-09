@extends('layouts.auth')
@section('title', 'Admin dashboard demo · FarSell')

@section('content')
    @include('demo.dashboard', ['panel' => 'admin', 'heading' => 'Admin dashboard'])
@endsection
