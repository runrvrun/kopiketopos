@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-center"><img src="{{ asset('images/check.gif') }}" style="width:75%"/></div>
    <div class="d-flex justify-content-center"><h3>Transaction Complete</h3></div>
</div>
@endsection
@section('pagecss')
@endsection
@section('pagejs')
<script>
    $(document).ready(function() {
        $(document).on("click",function() {
            window.location.href = "{{ url('/home') }}";
        });
    })
</script>
@endsection