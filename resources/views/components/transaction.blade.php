<div class="row py-2 transaction">
    <div class="col-10">
        <div><span class="count">{{ $count ?? 0 }}</span> items 
        @if(Auth::user()->role == 'admin')
        <span style="margin-left:30px;color:#56392C">{{ $store ?? '' }}</span>
        @endif
        </div>
        <div class="rp">Rp<span class="subtotal">{{ number_format($subtotal,0,',','.') }}</span></div>
    </div>
    @if($button == "edit")
    <div class="col-2">
    @if($status == 'Paid')
    <a href="{{url('transaction/receipt/'.$id)}}"><i class="fa fa-money"></i></a>
    @else
    <a href="{{ route('transaction.edit',$id) }}"><i class="fa fa-pencil"></i></a>
    @endif
    @if(isset($date))
    <div class="date">{{ $date }}</div>
    @endif
    </div>
    @endif
</div>