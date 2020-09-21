@extends('layouts.app')

@section('content')
<div class="container">    
    <x-backnav title="Ongoing Transaction" button="add" backlink="{{ route('home') }}"/>
    @foreach($items as $item)
    <x-transaction id="{{ $item->id }}" count="{{ $item->total_item }}" subtotal="{{ $item->total_price }}" button="edit" date="{{ $item->created_at->format('d/m') }}"/>
    <div class="separator"></div>
    @endforeach
    <x-bnav current="ongoing"/>
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
