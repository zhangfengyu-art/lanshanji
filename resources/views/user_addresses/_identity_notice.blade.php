<div class="alert address-identity-notice" role="alert">
  <p class="address-identity-notice__text">
    <i class="glyphicon glyphicon-exclamation-sign"></i>
    <strong>{{ trans('frontend.address.identity_notice_title') }}</strong>
    {{ trans('frontend.address.identity_notice_text') }}
  </p>
</div>

<style>
  .address-identity-notice {
    border: 2px solid #e67e22;
    background: #fff3cd;
    color: #333;
    font-size: 15px;
    line-height: 1.7;
    margin-bottom: 20px;
    padding: 14px 16px;
  }
  .address-identity-notice__text {
    margin: 0;
    color: #333;
  }
  .address-identity-notice__text .glyphicon {
    color: #c0392b;
    margin-right: 6px;
  }
  .address-identity-notice__text strong {
    color: #c0392b;
    font-size: 16px;
  }
</style>
