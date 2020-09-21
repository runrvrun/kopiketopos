<div {{ $attributes->merge(['class' => 'row producthalfside']) }}>
        <div class="col-4 product-img"><img src="{{ asset($item->image) }}" onerror="this.src='{{ asset('/uploads/product/default.png') }}'"/></div>
        <div class="col-8 product-detail">
            <div class="product-title">{{ $item->name }}</div>
        </div>
</div>