<div class="row py-2 transaction">
    <div class="col-10">
        <div><span class="count">{{ $count ?? 0 }}</span> items</div>
        <div class="rp">Rp<span class="subtotal">{{ number_format($subtotal,0,',','.') }}</span></div>
    </div>
    @if($button == "edit")
    <div class="col-2">
    @if($status == 'Paid')
    <i class="fa fa-money"></i>
    @else
    <a href="{{ route('transaction.edit',$id) }}"><i class="fa fa-pencil"></i></a>
    @endif
    @if(isset($date))
    <div class="date">{{ $date }}</div>
    @endif
    </div>
    @endif
</div>