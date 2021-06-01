@extends('layouts.app')

@section('content')
<div class="container">
    <x-backnav title="Transaction" backlink="{{ route('home') }}"/>
    @if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif
    @if(isset($trx))
    {{ Form::model($trx, ['route' => ['transaction.update', $trx->id], 'method' => 'patch']) }}
    @else
    {{ Form::open(['route' => 'transaction.store']) }}
    @endif
    <div class="form-body" style="position: fixed;top:50px; background-color:#fff;width:100%;z-index:99">
        @if(isset($trx))
        <x-transaction count="{{ $trx->total_item ?? 0}}" subtotal="{{ $trx->total_price-$trx->discount-$trx->downpayment ?? 0 }}"/>
        <input type="hidden" name="count" value="{{ $trx->total_item ?? 0 }}" />
        @else
        <x-transaction count="0" subtotal="0"/>
        <input type="hidden" name="count" value="0" />
        @endif
    </div>
    <div style="margin-top:130px">
        <div class="row">
            <div class="col-2">Name</div>
            <div class="col-10">
                <input type="text" name="customer" placeholder="Customer Name" value="{{ $trx->customer ?? '' }}" class="form-control block" />
            </div>
        </div>
        <div class="row">
            <div class="col-2">Total</div>
            <div class="col-10">
                <input type="text" name="totalprice" value="{{ $trx->total_price ?? 0 }}" class="form-control block" readonly />
            </div>
        </div>
        <div class="row">
            <div class="col-2">DP</div>
            <div class="col-10">
                <input type="text" name="downpayment" placeholder="Down payment" value="{{ $trx->downpayment ?? '' }}" class="form-control block" />
            </div>
        </div>
        <div class="row">
            <div class="col-2">Disc</div>
            <div class="col-10">
                <input type="text" name="discount" placeholder="Discount" value="{{ $trx->discount ?? '' }}" class="form-control block" />
            </div>
        </div>
    </div>
    <div>
        @foreach($items as $ii)
        <x-productorder :item="$ii" class="btn-number" data-type="plus" data-id="{{$ii->id}}" data-price="{{$ii->price}}" data-field="quant[{{ $ii->id }}]"/>
        @endforeach
    </div>
    <div class="form-actions bnav row p-0">        
        <div class="col-3 p-0 mx-1">
        <button type="submit" name="save" class="btn btn-block btn-raised btn-success">
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
        @if(isset($trx))
        <form method="POST" action="{{ route('transaction.destroy',$trx) }}">
        {{ csrf_field() }}
        {{ method_field('DELETE') }}
            <a class="danger" onclick="if(confirm('Hapus {{ $trx->name }}?')) this.closest('form').submit()">
                <button type="submit" class="btn btn-block btn-raised btn-danger">
                    <i class="fa fa-trash"></i>
                </button>
            </a>
        </form>
        @endif
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
    var count = parseInt($('.count').html().replace(/\./g,''));
    var totalprice = parseInt($('input[name=totalprice]').val());    
    fieldName = $(this).data('field');
    type      = $(this).data('type');
    price      = $(this).data('price');
    var input = $("input[name='"+fieldName+"']");
    var currentVal = parseInt(input.val());
    if (!isNaN(currentVal)) {
        if(type == 'minus') {
            
            if(currentVal > input.attr('min')) {
                input.val(currentVal - 1).change();
                count = count - 1;
                newtotal = totalprice - price;
                $('input[name=count]').val(count);
                $('input[name=totalprice]').val(newtotal);
                $('.count').html(count);
                calcpayment();
            } 
            if(parseInt(input.val()) == input.attr('min')) {
                $(this).attr('disabled', true);
            }

        } else if(type == 'plus') {

            if(currentVal < input.attr('max')) {
                input.val(currentVal + 1).change();
                count = count + 1;
                newtotal = totalprice + price;
                $('input[name=count]').val(count);
                $('input[name=totalprice]').val(newtotal);
                $('.count').html(count);
                calcpayment();
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
    var count = parseInt($('.count').html().replace(/\./g,''));
    var totalprice = parseInt($('input[name=totalprice]').val());
    minValue =  parseInt($(this).attr('min'));
    maxValue =  parseInt($(this).attr('max'));
    valueCurrent = parseInt($(this).val());
    oldvalue = $(this).data('oldvalue');
    price      = $(this).data('price');
    
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
    newtotal = totalprice + (price * (valueCurrent - oldvalue));
    $('input[name=count]').val(count);
    $('input[name=totalprice]').val(newtotal);
    $('.count').html(count);
    calcpayment();
    
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
$('input[name=downpayment]').change(function() {
    calcpayment();
});
$('input[name=discount]').change(function() {
    calcpayment();
});

function calcpayment() {
    dp = $('input[name=downpayment]').val();
    discount = $('input[name=discount]').val();
    totalprice = parseInt($('input[name=totalprice]').val());
    $('.subtotal').html(number_format(totalprice-discount-dp));
}
</script>
@endsection
