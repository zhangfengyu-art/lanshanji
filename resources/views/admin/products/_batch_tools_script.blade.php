(function () {
  var routes = {
    logistics: '{{ admin_url('products/batch/logistics') }}',
    'purchase-limit': '{{ admin_url('products/batch/purchase-limit') }}',
    'adjust-price': '{{ admin_url('products/batch/adjust-price') }}'
  };

  $(document).on('click', '[data-batch-action]', function (e) {
    e.preventDefault();
    if (!window.AdminBatch) {
      alert('页面脚本未加载完成，请刷新后重试');
      return;
    }

    var action = $(this).data('batch-action');
    var url = routes[action];
    if (!url) {
      return;
    }

    if (action === 'logistics') {
      var weight = $('.batch-weight-grams').val();
      if (!weight || parseInt(weight, 10) < 1) {
        alert('请填写单位重量（克）');
        return;
      }
      window.AdminBatch.post(url, {
        unit_weight_grams: weight,
        only_empty: 0
      }, { emptyMsg: '请先勾选商品' });
      return;
    }

    if (action === 'purchase-limit') {
      var limit = $('.batch-limit-only').val();
      if (!limit || parseInt(limit, 10) < 1) {
        alert('请填写限购数量');
        return;
      }
      window.AdminBatch.post(url, { purchase_limit: limit }, { emptyMsg: '请先勾选商品' });
      return;
    }

    if (action === 'adjust-price') {
      var val = $('.batch-price-value').val();
      if (val === '' || isNaN(val)) {
        alert('请填写调价数值');
        return;
      }
      var priceMode = $('.batch-price-mode').val();
      var hint = priceMode === 'percent' ? val + '%' : val + ' 日元';
      window.AdminBatch.post(url, { mode: priceMode, value: val }, {
        emptyMsg: '请先勾选商品',
        confirm: '确认对选中商品所有 SKU 调价：' + hint + '？'
      });
    }
  });
})();
