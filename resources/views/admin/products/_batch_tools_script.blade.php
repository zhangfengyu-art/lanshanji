(function () {
  function selectedIds() {
    var ids = [];
    $('.grid-row-checkbox').each(function () {
      var $cb = $(this);
      if (!$cb.prop('checked') && !$cb.parent().hasClass('checked')) {
        return;
      }
      var id = $cb.data('id');
      if (id !== undefined && id !== null && id !== '') {
        ids.push(id);
      }
    });
    return ids;
  }

  function postBatch(url, data, confirmText) {
    var ids = selectedIds();
    if (!ids.length) {
      alert('请先勾选商品');
      return;
    }
    if (confirmText && !confirm(confirmText)) {
      return;
    }
    data = data || {};
    data._token = typeof LA !== 'undefined' && LA.token ? LA.token : $('meta[name="csrf-token"]').attr('content');
    data.ids = ids;
    $.ajax({
      url: url,
      type: 'POST',
      data: data,
      dataType: 'json'
    }).done(function (ret) {
      if (ret && ret.status) {
        if (ret.message) {
          alert(ret.message);
        }
        $.pjax.reload('#pjax-container');
        return;
      }
      alert((ret && ret.message) ? ret.message : '操作失败');
    }).fail(function (xhr) {
      var ret = xhr.responseJSON || {};
      alert(ret.message || ('请求失败（HTTP ' + xhr.status + '）'));
    });
  }

  var ADMIN_BASE = '{{ admin_base_path() }}';
  var routes = {
    logistics: ADMIN_BASE + '/products/batch/logistics',
    'purchase-limit': ADMIN_BASE + '/products/batch/purchase-limit',
    'adjust-price': ADMIN_BASE + '/products/batch/adjust-price'
  };

  $(document).on('click', '[data-batch-action]', function (e) {
    e.preventDefault();
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
      postBatch(url, {
        unit_weight_grams: weight,
        only_empty: 0
      });
      return;
    }

    if (action === 'purchase-limit') {
      var limit = $('.batch-limit-only').val();
      if (!limit || parseInt(limit, 10) < 1) {
        alert('请填写限购数量');
        return;
      }
      postBatch(url, { purchase_limit: limit });
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
      postBatch(url, { mode: priceMode, value: val }, '确认对选中商品所有 SKU 调价：' + hint + '？');
    }
  });
})();
