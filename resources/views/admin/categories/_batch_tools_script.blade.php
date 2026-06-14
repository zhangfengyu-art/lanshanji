(function () {
  var ADMIN_BASE = '{{ admin_base_path() }}';
  $(document).on('click', '[data-category-batch]', function (e) {
    e.preventDefault();
    if (!window.AdminBatch) {
      return;
    }
    var action = $(this).data('category-batch');

    if (action === 'shipping-mode') {
      var mode = $('.batch-cat-shipping-mode').val();
      if (!mode) {
        alert('请选择寄送模式');
        return;
      }
      window.AdminBatch.post(ADMIN_BASE + '/categories/batch/shipping-mode', { shipping_mode: mode }, { emptyMsg: '请先勾选分类' });
      return;
    }

    if (action === 'set-directory') {
      var isDir = $(this).data('is-directory');
      window.AdminBatch.post(ADMIN_BASE + '/categories/batch/directory', { is_directory: isDir }, { emptyMsg: '请先勾选分类' });
      return;
    }

    if (action === 'move-parent') {
      var parentId = $('.batch-cat-parent').val();
      if (parentId === '') {
        alert('请选择目标父分类');
        return;
      }
      window.AdminBatch.post(ADMIN_BASE + '/categories/batch/move-parent', { parent_id: parentId }, {
        emptyMsg: '请先勾选分类',
        confirm: '确认移动选中分类？'
      });
    }
  });
})();
