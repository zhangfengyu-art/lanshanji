<div class="alert alert-warning address-identity-notice" role="alert">
  <p class="address-identity-notice__title">
    <i class="glyphicon glyphicon-exclamation-sign"></i>
  {{ trans('frontend.address.identity_notice_title') }}
  </p>
  <p class="address-identity-notice__text">{{ trans('frontend.address.identity_notice_text') }}</p>
</div>

<style>
  .address-identity-notice {
    border: 2px solid #f0ad4e;
    background: #fff8e6;
    color: #8a6d3b;
    font-size: 15px;
    line-height: 1.65;
    margin-bottom: 20px;
  }
  .address-identity-notice__title {
    margin: 0 0 8px;
    font-size: 16px;
    font-weight: bold;
    color: #a94442;
  }
  .address-identity-notice__title .glyphicon {
    margin-right: 6px;
  }
  .address-identity-notice__text {
    margin: 0;
  }
</style>
