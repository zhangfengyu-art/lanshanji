@extends('layouts.app')
@section('title', trans('frontend.order.product_reviews'))

@section('styles')
<style>
  body.site-mode-b .b-review-shell {
    max-width: 1040px;
    margin: 6px auto 24px;
  }

  body.site-mode-b .b-review-panel.panel {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 16px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
    overflow: hidden;
  }

  body.site-mode-b .b-review-panel .panel-heading {
    padding: 14px 18px;
    background: linear-gradient(135deg, #ffffff 0%, #f7fafc 100%);
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    font-size: 16px;
    font-weight: 700;
  }

  body.site-mode-b .b-review-panel .panel-body {
    padding: 14px 16px 18px;
  }

  body.site-mode-b .b-review-table {
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
  }

  body.site-mode-b .b-review-table > tbody > tr:first-child > td {
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    border-top: 0;
  }

  body.site-mode-b .b-review-table > tbody > tr > td {
    border-top: 1px solid rgba(15, 23, 42, 0.08);
    vertical-align: middle;
  }

  body.site-mode-b .product-info {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 300px;
  }

  body.site-mode-b .product-info .preview {
    flex-shrink: 0;
    width: 58px;
    height: 58px;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #eef3fb;
  }

  body.site-mode-b .product-info .preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  body.site-mode-b .product-title a {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
  }

  body.site-mode-b .sku-title {
    margin-top: 2px;
    color: #64748b;
    font-size: 12px;
    display: block;
  }

  body.site-mode-b .rate-area {
    margin: 0;
  }

  body.site-mode-b textarea.form-control {
    border-radius: 10px;
    border-color: #dbe2ea;
    min-height: 92px;
  }

  body.site-mode-b .b-review-table tfoot .btn {
    border-radius: 10px;
    min-width: 130px;
    font-weight: 700;
    padding: 9px 14px;
  }

  @media (max-width: 768px) {
    body.site-mode-b .b-review-shell {
      margin: 2px auto 14px;
    }

    body.site-mode-b .b-review-panel .panel-heading {
      padding: 12px 14px;
      font-size: 15px;
    }

    body.site-mode-b .b-review-panel .panel-body {
      padding: 10px;
    }

    body.site-mode-b .b-review-table > tbody > tr {
      display: block;
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 12px;
      margin-bottom: 10px;
      overflow: hidden;
    }

    body.site-mode-b .b-review-table > tbody > tr:first-child {
      display: none;
    }

    body.site-mode-b .b-review-table > tbody > tr > td {
      display: block;
      border-top: 1px dashed rgba(15, 23, 42, 0.08);
      padding: 10px;
    }

    body.site-mode-b .b-review-table > tbody > tr > td:first-child {
      border-top: 0;
    }

    body.site-mode-b .product-info {
      min-width: 0;
    }
  }
</style>
@endsection

@section('content')
<div class="row b-review-shell">
<div class="col-lg-10 col-lg-offset-1">
<div class="panel panel-default b-review-panel">
  <div class="panel-heading">
    {{ trans('frontend.order.product_reviews') }}
    <a class="pull-right" href="{{ route('orders.index') }}">{{ trans('frontend.order.back_to_orders') }}</a>
  </div>
  <div class="panel-body">
    <form action="{{ route('orders.review.store', [$order->id]) }}" method="post">
    <input type="hidden" name="_token" value="{{ csrf_token() }}">
    <table class="table b-review-table">
      <tbody>
      <tr>
        <td>{{ trans('frontend.order.product_name') }}</td>
        <td>{{ trans('frontend.order.score') }}</td>
        <td>{{ trans('frontend.order.review_column') }}</td>
      </tr>
      @foreach($order->items as $index => $item)
      <tr>
        <td class="product-info">
          @php
            $product = $item->product;
            $productImageUrl = $product ? $product->image_url : asset('images/brand-logo.svg');
            $productTitle = $product ? $product->title : trans('frontend.product.product_deleted');
          @endphp
          <div class="preview">
            <a target="_blank" href="{{ route('products.show', [$item->product_id]) }}">
              <img src="{{ $productImageUrl }}">
            </a>
          </div>
          <div>
            <span class="product-title">
               <a target="_blank" href="{{ route('products.show', [$item->product_id]) }}">{{ $productTitle }}</a>
             </span>
            <span class="sku-title">{{ optional($item->productSku)->title }}</span>
          </div>
          <input type="hidden" name="reviews[{{$index}}][id]" value="{{ $item->id }}">
        </td>
        <td class="vertical-middle">
          <!-- 如果订单已经评价则展示评分，下同 -->
          @if($order->reviewed)
          <span class="rating-star-yes">{{ str_repeat('★', $item->rating) }}</span><span class="rating-star-no">{{ str_repeat('★', 5 - $item->rating) }}</span>
          @else
          <ul class="rate-area">
            <input type="radio" id="5-star-{{$index}}" name="reviews[{{$index}}][rating]" value="5" checked /><label for="5-star-{{$index}}"></label>
            <input type="radio" id="4-star-{{$index}}" name="reviews[{{$index}}][rating]" value="4" /><label for="4-star-{{$index}}"></label>
            <input type="radio" id="3-star-{{$index}}" name="reviews[{{$index}}][rating]" value="3" /><label for="3-star-{{$index}}"></label>
            <input type="radio" id="2-star-{{$index}}" name="reviews[{{$index}}][rating]" value="2" /><label for="2-star-{{$index}}"></label>
            <input type="radio" id="1-star-{{$index}}" name="reviews[{{$index}}][rating]" value="1" /><label for="1-star-{{$index}}"></label>
          </ul>
          @endif
        </td>
        <td class="{{ $errors->has('reviews.'.$index.'.review') ? 'has-error' : '' }}">
          @if($order->reviewed)
          {{ $item->review }}
          @else
            <textarea class="form-control" name="reviews[{{$index}}][review]"></textarea>
            @if($errors->has('reviews.'.$index.'.review'))
              @foreach($errors->get('reviews.'.$index.'.review') as $msg)
                <span class="help-block">{{ $msg }}</span>
              @endforeach
            @endif
          @endif
        </td>
      </tr>
      @endforeach
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" class="text-center">
            @if(!$order->reviewed)
            <button type="submit" class="btn btn-primary center-block">{{ trans('frontend.common.submit') }}</button>
            @else
            <a href="{{ route('orders.show', [$order->id]) }}" class="btn btn-primary">{{ trans('frontend.order.view_order') }}</a>
            @endif
          </td>
        </tr>
      </tfoot>
    </table>
    </form>
  </div>
</div>
</div>
</div>
@endsection