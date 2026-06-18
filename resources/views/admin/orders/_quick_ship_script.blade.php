(function () {
  $(document).on('submit', '.order-quick-ship-form', function () {
    var $btn = $(this).find('.order-quick-ship-btn');
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> 提交中');
  });
})();
