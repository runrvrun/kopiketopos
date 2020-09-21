@extends('layouts.app')

@section('content')
<div class="container">
    <x-backnav title="New Transaction" backlink="{{ route('home') }}"/>
    @if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif
    {{ Form::open(['route' => 'transaction.store']) }}
    <div class="form-body">
        <x-transaction/>
        <input type="hidden" name="count" />
        <input type="hidden" name="subtotal" />
        <input type="hidden" name="selecteditems" />
    </div>
    <div>
    @foreach($products->chunk(2) as $items)
    <div class="row">
        @foreach($items as $p)
             <div class="col-6"><x-producthalfside :item="$p" class="add-product" data-id="{{$p->id}}" data-price="{{$p->price}}" /></div>
        @endforeach
    </div>
    @endforeach
    </div>
    <div class="form-actions bnav">        
        <button type="submit" class="pull-left btn btn-raised btn-primary btn-block mr-3">
            Save
        </button>
    </div>    
    </form>
</div>
@endsection
@section('pagecss')
@endsection
@section('pagejs')
<script>
    var count = parseInt($('.count').html().replace(/\./g,''));
    var subtotal = parseInt($('.subtotal').html().replace(/\./g,''));
    var selecteditems = $('input[name=selecteditems]').val();
    selecteditems = selecteditems.split(',').filter(Boolean);
    $('.add-product').click(function(){
        count = Number(count) + 1;
        subtotal = Number(subtotal) + $(this).data('price');
        selecteditems.push($(this).data('id'));
        $('.count').html(count);
        $('.subtotal').html(number_format(subtotal));
        $('input[name=count]').val(count);
        $('input[name=subtotal]').val(subtotal);
        $('input[name=selecteditems]').val(selecteditems.sort().join(','));
    });
</script>
@endsection
