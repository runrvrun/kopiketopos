@extends('layouts.app')

@section('content')
<div class="container">
    <nav class="tnav">
        <img class="logo" src="{{asset('images/logo-tnav.png')}}"/>            
    </nav>
    <div class="row primarycolorbg py-2">
        <div class="col-12">
            <div>Penjualan hari ini</div>
            <div class="rp">Rp<span>{{ $data['sumdaysales'] }}</span></div>
        </div>
    </div>
    <div class="row py-2">
        <div class="col-12">
            <div>Penjualan bulan ini</div>
            <div class="rp">Rp<span>{{ $data['summonthsales'] }}</span></div>
        </div>
    </div>
    <x-bnav current="home"/>
</div>
@endsection
@section('pagecss')
<style>
body{
    margin-top: 50px;
    margin-bottom: 50px;
}
</style>
@endsection
