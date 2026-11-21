@extends('layouts.app')

@section('content')
<div class="container">
    <x-backnav title="Download History" backlink="{{ url('more') }}"/>
    @if(Session::has('message'))
    <div class="alert {{ Session::get('alert-class', 'alert-info') }} alert-dismissible">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    {{ ucfirst(Session::get('message')) }}
    </div>
    @endif
    <div class="row py-3">
    <div class="col-12 offset-1">Select Month</div>
    </div>
    <div class="row">
    <div class="col-12">
        <form action="{{ url('transaction/generatedownloadhistory') }}">
        <select name="month" class="form-control">
        <?php
            $i = 0;
            $month = 0;
        ?>
        @while($month != 202008)        
            <?php
                $month = \Carbon\Carbon::now()->startOfMonth()->subMonth($i)->format('Ym'); 
                $monthname = \Carbon\Carbon::now()->startOfMonth()->subMonth($i)->format('F Y');
                $i = $i+1;
            ?>
            <option value="{{ $month }}">{{ $monthname }}</option>
        @endwhile
        </select>
        <input type="submit" value="Download" class="form-control mt-2 btn btn-primary" />
        </form>
    </div>
    </div>
    <x-bnav current="more"/>
</div>
@endsection
@section('pagecss')
@endsection
