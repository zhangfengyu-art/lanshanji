(function () {
  $(document).on('click', '[data-order-fp-upload]', function () {
    var target = $(this).data('target');
    if (target) {
      $(target).trigger('click');
    }
  });

  $(document).on('change', '[data-order-fp-input]', function () {
    if (!this.files || !this.files.length) {
      return;
    }
    var $btn = $(this).closest('form').find('[data-order-fp-upload]');
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> 上传中');
    this.form.submit();
  });
})();
