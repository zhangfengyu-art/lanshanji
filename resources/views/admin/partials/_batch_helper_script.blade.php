(function () {
  if (window.AdminBatch && window.AdminBatch._loaded) {
    return;
  }

  window.AdminBatch = {
    _loaded: true,
    selectedIds: function () {
      var ids = [];
      var seen = {};
      $('.grid-row-checkbox').each(function () {
        var $cb = $(this);
        if (!$cb.prop('checked') && !$cb.parent().hasClass('checked')) {
          return;
        }
        var id = $cb.data('id');
        if (id === undefined || id === null || id === '') {
          return;
        }
        id = String(id);
        if (seen[id]) {
          return;
        }
        seen[id] = true;
        ids.push(id);
      });
      return ids;
    },
    post: function (url, data, options) {
      options = options || {};
      if (this._posting) {
        return;
      }
      var ids = this.selectedIds();
      if (!ids.length) {
        alert(options.emptyMsg || '请先勾选记录');
        return;
      }
      if (options.confirm && !confirm(options.confirm)) {
        return;
      }
      data = data || {};
      data._token = typeof LA !== 'undefined' && LA.token ? LA.token : $('meta[name="csrf-token"]').attr('content');
      data.ids = ids;
      this._posting = true;
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
      }).always(function () {
        window.AdminBatch._posting = false;
      });
    }
  };
})();
