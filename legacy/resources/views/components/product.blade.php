    <div class="row product">
        <div {{ $attributes->merge(['class' => 'col-10']) }}>
            <div class="product-img"><img src="{{ asset($item->image) }}" onerror="this.src='{{ asset('/uploads/product/default.png') }}'"/></div>
            <div class="product-detail">
                <div class="product-title">{{ $item->name }}</div>
                <div class="product-price"><span class="rp">Rp</span>{{ number_format($item->price,0,',','.') }}</div>
            </div>
        </div>
        @if($button=='edit')
        <div class="col-2"><a href="{{ route('product.edit',$item) }}"><i class="fa fa-pencil"></i></a></div>        
        @endif
    </div>
    
    @if($button=='plusminus')
    <div class="row plusminus">
        <div class="col-6 offset-6">
        <div class="input-group">
          <span class="input-group-btn">
              <button type="button" class="btn btn-default btn-number" data-type="minus" data-field="quant[{{ $item->id }}]" data-price="{{ $item->price }}">
                  <span class="fa fa-minus"></span>
              </button>
          </span>
          <input type="text" name="quant[{{ $item->id }}]" class="form-control input-number" value="{{ $item->amount ?? 0 }}" min="0" max="99"  data-oldvalue="" data-price="{{ $item->price }}">
          <span class="input-group-btn">
              <button type="button" class="btn btn-default btn-number" data-type="plus" data-field="quant[{{ $item->id }}]" data-price="{{ $item->price }}">
                  <span class="fa fa-plus"></span>
              </button>
          </span>
        </div>
        </div>
    </div>
    <div class="separator"></div>
    @else
    <div class="separator"></div>
    @endif