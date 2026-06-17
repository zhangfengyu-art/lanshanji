@if(is_site_mode_a())
(function () {
  var routes = {
    'start-processing': '{{ admin_url('orders/batch/start-processing') }}',
    lock: '{{ admin_url('orders/batch/lock') }}',
    unlock: '{{ admin_url('orders/batch/unlock') }}',
    'logistics-warehouse': '{{ admin_url('orders/batch/logistics-warehouse') }}'
  };

  var confirms = {
    'start-processing': '将选中且处于「待处理 S1」的订单批量标记为开始处理（S2），继续？',
    lock: '将选中且处于 S1/S2 的订单批量进入备货/打包（S3），继续？',
    unlock: '将选中且可退回的 S3 订单批量退回上一阶段，继续？',
    'logistics-warehouse': '将选中且处于 S2/S3 的订单批量标记为「已送往物流仓库」，继续？'
  };

  $(document).on('click', '[data-order-batch]', function (e) {
    e.preventDefault();
    if (!window.AdminBatch) {
      alert('页面脚本未加载完成，请刷新后重试');
      return;
    }

    var action = $(this).data('order-batch');
    var url = routes[action];
    if (!url) {
      return;
    }

    window.AdminBatch.post(url, {}, {
      emptyMsg: '请先勾选订单',
      confirm: confirms[action] || '确认继续？'
    });
  });
})();
@endif
