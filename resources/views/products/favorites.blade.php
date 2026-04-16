@extends('layouts.app')
@section('title', trans('frontend.nav.my_favorites'))

@section('content')
<div class="row">
<div class="col-lg-10 col-lg-offset-1">
<div class="panel panel-default">
  <div class="panel-heading">{{ trans('frontend.nav.my_favorites') }}</div>
  <div class="panel-body">
    <div class="row products-list">
      @foreach($products as $product)
        <div class="col-xs-3 product-item">
          <div class="product-content">
            <div class="top">
              <div class="img">
                <a href="{{ route('products.show', ['product' => $product->id]) }}">
                  <img src="{{ $product->image_url }}" alt="">
                </a>
              </div>
              <div class="price">{{ number_format($product->price, 2, '.', '') }}日元</div>
              <a href="{{ route('products.show', ['product' => $product->id]) }}">{{ $product->title }}</a>
            </div>
            <div class="bottom">
              <div class="sold_count">{{ trans('frontend.product.sold_count') }} <span>{{ $product->sold_count }}{{ trans('frontend.cart.item_unit') }}</span></div>
              <div class="review_count">{{ trans('frontend.product.review_count') }} <span>{{ $product->review_count }}</span></div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
    <div class="pull-right">{{ $products->render() }}</div>
  </div>  
</div>
</div>
</div>
@endsection