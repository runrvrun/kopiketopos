@extends('layouts.app')

@section('content')
<div class="container">
    <x-backnav title="Product" button="add" backlink="{{ url('/more') }}"/>
    @if(Session::has('message'))
    <div class="alert {{ Session::get('alert-class', 'alert-info') }} alert-dismissible">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    {{ ucfirst(Session::get('message')) }}
    </div>
    @endif
    @foreach($products as $item)
    <x-product :item="$item" button="edit"/>
    @endforeach
</div>
@endsection
@section('pagecss')
@endsection
