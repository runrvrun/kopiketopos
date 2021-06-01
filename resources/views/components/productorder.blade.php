<div class="row">
    <div class="product col-8">
        <div {{ $attributes->merge(['class' => 'col-12']) }}>
            <div class="product-img"><img src="{{ asset($item->image) }}" onerror="this.src='{{ asset('/uploads/product/default.png') }}'"/></div>
            <div class="product-detail">
                <div class="product-title">{{ $item->name }}</div>
                <div class="product-price"><span class="rp">Rp</span>{{ number_format($item->price,0,',','.') }}</div>
            </div>
        </div>
    </div>
    
    <div class="plusminus col-4">
            <div class="input-group">
                <input type="text" name="quant[{{ $item->id }}]" class="form-control input-number" value="{{ $item->amount ?? 0 }}" min="0" max="99"  data-oldvalue="" data-price="{{ $item->price }}">
            </div>
            <div class="input-group">
            <span class="input-group-btn" style="border:1px solid #ccc">
                <button type="button" class="btn btn-default btn-number" data-type="minus" data-field="quant[{{ $item->id }}]" data-price="{{ $item->price }}">
                    <span class="fa fa-minus"></span>
                </button>
            </span>
            <span class="input-group-btn" style="margin-left:3px;border:1px solid #ccc">
                <button type="button" class="btn btn-default btn-number" data-type="plus" data-field="quant[{{ $item->id }}]" data-price="{{ $item->price }}">
                    <span class="fa fa-plus"></span>
                </button>
            </span>
            </div>
    </div>
</div>
<div class="separator"></div>