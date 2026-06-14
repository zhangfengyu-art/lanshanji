(function () {
  var ADMIN_BASE = '{{ admin_base_path() }}';
  $(document).on('click', '[data-coupon-batch]', function (e) {
    e.preventDefault();
    if (!window.AdminBatch) {
      return;
    }
    var action = $(this).data('coupon-batch');
    if (action === 'enable') {
      window.AdminBatch.post(ADMIN_BASE + '/coupon_codes/batch/enabled', { enabled: 1 }, { emptyMsg: '请先勾选优惠券' });
      return;
    }
    if (action === 'disable') {
      window.AdminBatch.post(ADMIN_BASE + '/coupon_codes/batch/enabled', { enabled: 0 }, {
        emptyMsg: '请先勾选优惠券',
        confirm: '确认停用选中的优惠券？'
      });
      return;
    }
    if (action === 'add-total') {
      var amount = $('.batch-coupon-add-total').val();
      if (!amount || parseInt(amount, 10) < 1) {
        alert('请填写增加的发放量');
        return;
      }
      window.AdminBatch.post(ADMIN_BASE + '/coupon_codes/batch/add-total', { amount: amount }, { emptyMsg: '请先勾选优惠券' });
      return;
    }
    if (action === 'extend-expiry') {
      var days = $('.batch-coupon-extend-days').val();
      if (!days || parseInt(days, 10) < 1) {
        alert('请填写延长天数');
        return;
      }
      window.AdminBatch.post(ADMIN_BASE + '/coupon_codes/batch/extend-expiry', { days: days }, { emptyMsg: '请先勾选优惠券' });
    }
  });
})();
