@extends('layouts.app')

@section('content')
<div class="container">
    @if(isset($item))
    <x-backnav title="Edit Product" backlink="{{ route('product.index') }}"/>
    @else
    <x-backnav title="Add Product" backlink="{{ route('product.index') }}"/>
    @endif
    @if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif
    @if(isset($item))
        {{ Form::model($item, ['route' => ['product.update', $item->id], 'method' => 'patch','enctype'=>'multipart/form-data']) }}
    @else
        {{ Form::open(['route' => 'product.store','enctype'=>'multipart/form-data']) }}
    @endif
    <div class="form-body">
        <img class="py-2" src="{{ asset($item->image ?? null) }}" onerror="this.src='{{ asset('/uploads/product/default.png') }}'" />
        <div class="form-group row my-2">
            <div class="col-md-9">
            {{ Form::file('image', array('class' => 'form-control')) }}
            </div>
        </div>
        <div class="form-group row my-2">
            <div class="col-md-9">
            {{ Form::text('name', old('name',$item->name ?? null), array('placeholder'=>'Product Name','class' => 'form-control','required')) }}
            </div>
        </div>
        <div class="form-group row my-2">
            <div class="col-md-9">
            {{ Form::text('price', old('price',$item->price ?? null), array('placeholder'=>'Price','class' => 'form-control','required')) }}
            </div>
        </div>
        <div class="form-group row my-2">
            <div class="col-md-9">
            <?php
                $category = [
                'Coffee-Based Drinks'=>'Coffee-Based Drinks',
                'Non-Coffee Drinks'=>'Non-Coffee Drinks',
                'Boba Series'=>'Boba Series',
                'Frappucino Series'=>'Frappucino Series',
                'Instant Series'=>'Instant Series',
                'Snacks'=>'Snacks'
                ];
            ?>
            {{ Form::select('category', $category, old('category',$item->category ?? null), array('placeholder'=>'Category','class' => 'form-control','required')) }}
            </div>
        </div>
    </div>
    <div class="form-actions">        
        <button type="submit" class="pull-left btn btn-raised btn-primary btn-block mr-3">
            <i class="fa fa-check-square-o"></i> Save
        </button>
    </div>
    </form>
    <div class="form-actions">        
        @if(isset($item))
        <form method="POST" action="{{ route('product.destroy',$item) }}">
        {{ csrf_field() }}
        {{ method_field('DELETE') }}
            <a class="danger p-0" onclick="if(confirm('Hapus {{ $item->name }}?')) this.closest('form').submit()">
                <button type="submit" class="pull-left btn btn-raised btn-danger btn-block mr-3 mt-4">
                    <i class="fa fa-trash"></i> Delete
                </button>
            </a>
        </form>
        @endif
    </div>
</div>
@endsection
@section('pagecss')
@endsection
