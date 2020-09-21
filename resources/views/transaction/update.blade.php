@extends('layouts.app')

@section('content')
<div class="container">
    <x-backnav title="Edit Transaction" backlink="{{ route('home') }}"/>
    @if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif
    {{ Form::model($item, ['route' => ['transaction.update', $item->id], 'method' => 'patch']) }}
    <div class="form-body">
        <x-transaction count="{{ $item->total_item }}" subtotal="{{ $item->total_price }}"/>
        <input type="hidden" name="count" value="{{ $item->total_item }}" />
        <input type="hidden" name="subtotal" value="{{ $item->total_price }}" />
    </div>
    <div>
        <input type="text" name="customer" placeholder="Customer Name" value="{{ $item->customer }}" class="form-control block" />
    </div>
    <div>
        @foreach($item2 as $ii)
        <x-product :item="$ii" button="plusminus" />
        @endforeach
    </div>
    <div class="form-actions bnav row p-0">        
        <div class="col-3 p-0 mx-1">
        <button type="submit" class="btn btn-block btn-raised btn-success">
            <i class="fa fa-save"></i>
        </button>
        </div>
        <div class="col-6 p-0">
        <button type="submit" name="payment" value="1" class="btn btn-block btn-raised btn-primary">
            Payment
        </button>
        </div>
    </form>
        <div class="col-2 p-0 pl-2">
        <form method="POST" action="{{ route('transaction.destroy',$item) }}">
        {{ csrf_field() }}
        {{ method_field('DELETE') }}
            <a class="danger" onclick="if(confirm('Hapus {{ $item->name }}?')) this.closest('form').submit()">
                <button type="submit" class="btn btn-block btn-raised btn-danger">
                    <i class="fa fa-trash"></i>
                </button>
            </a>
        </form>
        </div>
    </div>
</div>
@endsection
@section('pagecss')
@endsection
@section('pagejs')
<script>
$('.btn-number').click(function(e){
    e.preventDefault();
    
    fieldName = $(this).data('field');
    type      = $(this).data('type');
    price      = $(this).data('price');
    var input = $("input[name='"+fieldName+"']");
    var currentVal = parseInt(input.val());
    var count = parseInt($('.count').html().replace(/\./g,''));
    var subtotal = parseInt($('.subtotal').html().replace(/\./g,''));
    if (!isNaN(currentVal)) {
        if(type == 'minus') {
            
            if(currentVal > input.attr('min')) {
                input.val(currentVal - 1).change();
                count = count - 1;
                subtotal = subtotal - price;
                $('input[name=count]').val(count);
                $('input[name=subtotal]').val(subtotal);
                $('.count').html(count);
                $('.subtotal').html(number_format(subtotal));
            } 
            if(parseInt(input.val()) == input.attr('min')) {
                $(this).attr('disabled', true);
            }

        } else if(type == 'plus') {

            if(currentVal < input.attr('max')) {
                input.val(currentVal + 1).change();
                count = count + 1;
                subtotal = subtotal + price;
                $('input[name=count]').val(count);
                $('input[name=subtotal]').val(subtotal);
                $('.count').html(count);
                $('.subtotal').html(number_format(subtotal));
            }
            if(parseInt(input.val()) == input.attr('max')) {
                $(this).attr('disabled', true);
            }

        }
    } else {
        input.val(0);
    }
});
$('.input-number').on('focusin', function(){
   $(this).data('oldvalue', $(this).val());
});
$('.input-number').change(function() {
    
    minValue =  parseInt($(this).attr('min'));
    maxValue =  parseInt($(this).attr('max'));
    valueCurrent = parseInt($(this).val());
    oldvalue = $(this).data('oldvalue');
    price      = $(this).data('price');
    var count = parseInt($('.count').html().replace(/\./g,''));
    var subtotal = parseInt($('.subtotal').html().replace(/\./g,''));
    
    name = $(this).attr('name');
    if(valueCurrent >= minValue) {
        $(".btn-number[data-type='minus'][data-field='"+name+"']").removeAttr('disabled')
    } else {
        $(this).val($(this).data('oldvalue'));
    }
    if(valueCurrent <= maxValue) {
        $(".btn-number[data-type='plus'][data-field='"+name+"']").removeAttr('disabled')
    } else {
        $(this).val($(this).data('oldValue'));
    }
    
    count = count + valueCurrent - oldvalue;
    subtotal = subtotal + (price * (valueCurrent - oldvalue));
    $('input[name=count]').val(count);
    $('input[name=subtotal]').val(subtotal);
    $('.count').html(count);
    $('.subtotal').html(number_format(subtotal));
    
});
$(".input-number").keydown(function (e) {
    // Allow: backspace, delete, tab, escape, enter and .
    if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 190]) !== -1 ||
            // Allow: Ctrl+A
        (e.keyCode == 65 && e.ctrlKey === true) || 
            // Allow: home, end, left, right
        (e.keyCode >= 35 && e.keyCode <= 39)) {
                // let it happen, don't do anything
                return;
    }
    // Ensure that it is a number and stop the keypress
    if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
        e.preventDefault();
    }
});
</script>
@endsection
