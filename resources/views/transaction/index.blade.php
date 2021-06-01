@extends('layouts.app')

@section('content')
<div class="container">
    <x-backnav title="Transaction" button="add" backlink="{{ route('home') }}"/>
    @if(Session::has('message'))
    <div class="alert {{ Session::get('alert-class', 'alert-info') }} alert-dismissible">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    {{ ucfirst(Session::get('message')) }}
    </div>
    @endif
    @foreach($transactions as $item)
    <x-transaction id="{{ $item->id }}" store="{{ $item->store->name ?? ''}}" count="{{ $item->total_item }}" subtotal="{{ $item->total_price-$item->discount }}" status="{{ $item->status }}" button="edit" date="{{ $item->created_at->format('d/m') }}"/>
    <div class="separator"></div>
    @endforeach
    <x-bnav current="history"/>
</div>
@endsection
@section('pagecss')
@endsection
