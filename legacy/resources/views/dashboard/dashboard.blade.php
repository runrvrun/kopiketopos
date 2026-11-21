@extends('layouts.app')

@section('content')
<div class="container">
    <nav class="tnav">
        <img class="logo" src="{{asset('images/logo-tnav.png')}}"/>            
    </nav>
    @if(Auth::user()->role == 'admin')    
    <div><strong>All Stores</strong></div>
    <div class="row primarycolorbg py-2">
        <div class="col-12">
            <div>Penjualan hari ini</div>
            <div class="rp">Rp<span>{{ number_format($data['sumdaysales']-$data['sumdaydiscount'],0,',','.') }}</span></div>
        </div>
    </div>
    <div class="row py-2">
        <div class="col-12">
            <div>Penjualan bulan ini</div>
            <div class="rp">Rp<span>{{ number_format($data['summonthsales']-$data['summonthdiscount'],0,',','.') }}</span></div>
        </div>
    </div>
    <div class="row py-2">
        <div class="col-12">
            <div>Penjualan 3 bulan terakhir</div>
            <div class="rp">Rp<span>{{ number_format($data['sum3monthsales']-$data['sum3monthdiscount'],0,',','.') }}</span></div>
        </div>
    </div>
    @endif
    @foreach($data['stores'] as $store)
    @if(Auth::user()->role == 'admin' || Auth::user()->store_id == $store->id)   
    <div><strong>{{ $store->name }}</strong></div>
    @if(isset($data[$store->id])) 
    <div class="row primarycolorbg py-2">
        <div class="col-12">
            <div>Penjualan hari ini</div>
            <div class="rp">Rp<span>{{ number_format($data[$store->id]['sumdaysales']-$data[$store->id]['sumdaydiscount'],0,',','.') }}</span></div>
        </div>
    </div>
    <div class="row py-2">
        <div class="col-12">
            <div>Penjualan bulan ini</div>
            <div class="rp">Rp<span>{{ number_format($data[$store->id]['summonthsales']-$data[$store->id]['summonthdiscount'],0,',','.') }}</span></div>
        </div>
    </div>
    <div class="row py-2">
        <div class="col-12">
            <div>Penjualan 3 bulan terakhir</div>
            <div class="rp">Rp<span>{{ number_format($data[$store->id]['sum3monthsales']-$data[$store->id]['sum3monthdiscount'],0,',','.') }}</span></div>
        </div>
    </div>
    @else
    <div class="row py-2">
        <div class="col-12">
            <div>Belum ada penjualan</div>
        </div>
    </div>
    @endif
    @endif
    @endforeach
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
