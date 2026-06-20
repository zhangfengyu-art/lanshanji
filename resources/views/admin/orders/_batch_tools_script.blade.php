@if(is_site_mode_a())
(function () {
  if (window.__orderBatchToolsBound) {
    return;
  }
  window.__orderBatchToolsBound = true;

  var routes = {
    'start-processing': '{{ admin_url('orders/batch/start-processing') }}',
    lock: '{{ admin_url('orders/batch/lock') }}',
    unlock: '{{ admin_url('orders/batch/unlock') }}',
    'logistics-warehouse': '{{ admin_url('orders/batch/logistics-warehouse') }}'
  };

  var confirmTemplates = {
    'start-processing': '将选中的 %count% 笔订单（处于「待处理·未开始处理」的才会生效）批量标记为已开始处理，继续？',
    lock: '将选中的 %count% 笔订单（处于「待处理」的才会生效）批量进入备货/打包，继续？',
    unlock: '将选中的 %count% 笔订单（可退回的备货/打包订单才会生效）批量退回待处理，继续？',
    'logistics-warehouse': '将选中的 %count% 笔订单（处于备货/打包的才会生效）批量标记为「已送往物流仓库」，继续？'
  };

  $(document).off('click.orderBatchTools', '[data-order-batch]').on('click.orderBatchTools', '[data-order-batch]', function (e) {
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

    var ids = window.AdminBatch.selectedIds();
    if (!ids.length) {
      alert('请先勾选订单');
      return;
    }

    var template = confirmTemplates[action] || '将对选中的 %count% 笔订单执行操作，继续？';
    var confirmMsg = template.replace('%count%', ids.length);

    window.AdminBatch.post(url, {}, {
      emptyMsg: '请先勾选订单',
      confirm: confirmMsg
    });
  });
})();
@endif
