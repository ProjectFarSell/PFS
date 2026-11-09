@extends('layouts.auth')
@section('title', 'Rider dashboard demo · FarSell')

@section('content')
    @include('demo.dashboard', ['panel' => 'rider', 'heading' => 'Rider dashboard'])
@endsection
