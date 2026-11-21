@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center whitebg">
        <a href="{{ url('/') }}"><img class="login-logo" src="{{asset('images/logo.png')}}" style="height: 30px"/></a>
    </div>
    <div class="row justify-content-center text-center whitebg m-2">
        <p>Jl. Alam Asri 1 Blok H 35 No. 5<br>Pamulang, Tangerang Selatan<br>081213327373</p>
    </div>
    <div class="row">
        <div class="col-6"><i class="fa fa-calendar"></i> {{ $transaction->created_at->format('j/m/Y H:i') }}</div>
        <div class="col-6 text-right"><strong>INV-{{ $transaction->key ?? ''}}</strong></div>
    </div>
    <div class="customer my-1">{{$transaction->customer ?? ''}}</div>
    <div class="col-12">
        @foreach($products as $item)
        {{ $item->product->name }}
        <div class="row">
            <div class="col-1 offset-1">{{$item->amount}}</div>
            <div class="col-4 text-right">@ {{number_format($item->product->price,0,',','.')}}</div>
            <div class="col-4 text-right">{{number_format($item->amount * $item->product->price,0,',','.')}}</div>
        </div>
        @endforeach
        <hr>
        <div class="row">
            <div class="col-3 offset-3">TOTAL</div>
            <div class="col-4 text-right">{{number_format($transaction->total_price,0,',','.')}}</div>
        </div>
        <div class="row">
            <div class="col-3 offset-3">DISCOUNT</div>
            <div class="col-4 text-right">{{number_format($transaction->discount,0,',','.')}}</div>
        </div>
        <div class="row">
            <div class="col-3 offset-3">DP</div>
            <div class="col-4 text-right">{{number_format($transaction->downpayment,0,',','.')}}</div>
        </div>
        <div class="row">
            <div class="col-3 offset-3">PAYMENT</div>
            <div class="col-4 text-right">{{number_format($transaction->total_price-$transaction->discount-$transaction->downpayment,0,',','.')}}</div>
        </div>
    </div>
    <hr>
    <div class="my-3 text-center">Terima kasih sudah berbelanja di Kopi Keto<br>Salam Kopi Nusantara</div>
</div>
@endsection
@section('pagecss')
<style>
body{
    margin: 20px 30px;
    font-size: 9pt;
}
.customer{
    border-top:1px solid black;
    border-bottom:1px solid black;
}
hr{
    border-top: 1px solid black;
}
</style>
@endsection
@section('pagejs')
@endsection