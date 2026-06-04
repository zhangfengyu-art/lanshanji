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

  var routes = {
    category: '/admin/products/batch/category',
    'shipping-mode': '/admin/products/batch/shipping-mode',
    'tobacco-type': '/admin/products/batch/tobacco-type',
    'sale-status': '/admin/products/batch/sale-status',
    'on-sale': '/admin/products/batch/on-sale',
    logistics: '/admin/products/batch/logistics',
    'purchase-limit': '/admin/products/batch/purchase-limit',
    'inherit-category': '/admin/products/batch/inherit-category',
    'adjust-price': '/admin/products/batch/adjust-price'
  };

  $(document).on('change', '.batch-sale-status', function () {
    var isLimited = $(this).val() === 'LIMITED';
    $('.batch-purchase-limit').toggle(isLimited);
  });

  $(document).on('click', '[data-batch-action]', function (e) {
    e.preventDefault();
    var action = $(this).data('batch-action');
    var url = routes[action];
    if (!url) {
      return;
    }

    if (action === 'category') {
      var categoryId = $('.batch-category-select').val();
      if (!categoryId) {
        alert('请选择目标分类');
        return;
      }
      postBatch(url, { category_id: categoryId });
      return;
    }

    if (action === 'shipping-mode') {
      var mode = $('.batch-shipping-mode').val();
      if (!mode) {
        alert('请选择寄送模式');
        return;
      }
      postBatch(url, { shipping_mode: mode });
      return;
    }

    if (action === 'tobacco-type') {
      var type = $('.batch-tobacco-type').val();
      if (!type) {
        alert('请选择烟草分类');
        return;
      }
      postBatch(url, { tobacco_type: type });
      return;
    }

    if (action === 'sale-status') {
      var status = $('.batch-sale-status').val();
      if (!status) {
        alert('请选择销售状态');
        return;
      }
      var payload = { sale_status: status };
      if (status === 'LIMITED') {
        payload.purchase_limit = $('.batch-purchase-limit').val();
      }
      postBatch(url, payload);
      return;
    }

    if (action === 'on-sale') {
      postBatch(url, { on_sale: $(this).data('on-sale') });
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
        unit_sticks: $('.batch-unit-sticks').val(),
        only_empty: $('.batch-only-empty').prop('checked') ? 1 : 0
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

    if (action === 'inherit-category') {
      postBatch(url, {}, '将未单独设置寄送模式的商品，改为其所属分类的默认寄送模式。继续？');
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
