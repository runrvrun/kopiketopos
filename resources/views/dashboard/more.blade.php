@extends('layouts.app')

@section('content')
<div class="container">
    <nav class="tnav">
        <img class="logo" src="{{asset('images/logo-tnav.png')}}"/>            
    </nav>
    <div class="row item-list">
        <div class="col-12">
            <a href="{{url('product')}}"><i class="fa fa-coffee px-2"></i> Product <i class="fa fa-angle-right pull-right px-2"></i></a>
        </div>
    </div>
    <div class="row item-list">
        <div class="col-12">
            <a href="{{url('transaction/downloadhistory')}}"><i class="fa fa-download px-2"></i> Download History <i class="fa fa-angle-right pull-right px-2"></i></a>
        </div>
    </div>
    <x-bnav current="more"/>
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
