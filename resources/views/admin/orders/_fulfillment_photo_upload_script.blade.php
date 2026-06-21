(function () {
  if (window.__orderFpUploadBound) {
    return;
  }
  window.__orderFpUploadBound = true;

  function csrfToken() {
    if (typeof LA !== 'undefined' && LA.token) {
      return LA.token;
    }

    return $('meta[name="csrf-token"]').attr('content') || '';
  }

  function resetButton($btn, label) {
    if ($btn.hasClass('btn-primary')) {
      $btn.prop('disabled', false).html('<i class="fa fa-camera"></i> 上传实拍图');
      return;
    }

    $btn.prop('disabled', false).html('<i class="fa fa-camera"></i> <span data-order-fp-upload-label>' + label + '</span>');
  }

  function showMessage(type, message) {
    if (typeof toastr !== 'undefined') {
      toastr[type](message);
      return;
    }

    alert(message);
  }

  function isDetailUploadForm($form) {
    return $form.find('#fulfillment_photo[data-order-fp-input]').length > 0;
  }

  function cellHasPhoto($form) {
    return $form.closest('[data-order-fp-cell]').find('[data-order-fp-preview]').length > 0;
  }

  function updateCellPreview($cell, photoUrl, fullUrl) {
    $cell.find('[data-order-fp-placeholder]').remove();

    var $link = $cell.find('[data-order-fp-preview-link]');
    if (!$link.length) {
      $link = $('<a target="_blank" title="查看原图" data-order-fp-preview-link></a>');
      $cell.prepend($link);
    }

    $link.attr('href', fullUrl);
    var $img = $link.find('[data-order-fp-preview]');
    if (!$img.length) {
      $img = $('<img alt="实拍" data-order-fp-preview>');
      $link.empty().append($img);
    }

    $img.attr('src', photoUrl + (photoUrl.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now());
    $img.css({
      width: '48px',
      height: '48px',
      objectFit: 'cover',
      border: '1px solid #ddd',
      borderRadius: '4px',
      display: 'block',
      marginBottom: '4px'
    });

    var $label = $cell.find('[data-order-fp-upload-label]');
    if ($label.length) {
      $label.text('更换');
    }
    $cell.find('[data-order-fp-upload]').removeClass('btn-success').addClass('btn-default');
  }

  function uploadPhoto($form) {
    var $input = $form.find('[data-order-fp-input]');
    var $btn = $form.find('[data-order-fp-upload]');
    var file = $input[0] && $input[0].files ? $input[0].files[0] : null;
    var hadPhoto = cellHasPhoto($form);
    var detailPage = isDetailUploadForm($form);

    if (!file) {
      showMessage('warning', '请先选择或拍摄一张照片');
      return;
    }

    var formData = new FormData();
    formData.append('photo', file);
    formData.append('_token', csrfToken());

    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> 上传中');

    $.ajax({
      url: $form.attr('action'),
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    }).done(function (ret) {
      if (ret && ret.status) {
        showMessage('success', ret.message || '实拍照片已上传');

        if (detailPage) {
          window.location.reload();
          return;
        }

        var $cell = $form.closest('[data-order-fp-cell]');
        if ($cell.length && ret.photo_url && ret.photo_full_url) {
          updateCellPreview($cell, ret.photo_url, ret.photo_full_url);
        } else if ($.pjax) {
          $.pjax.reload('#pjax-container');
        } else {
          window.location.reload();
        }
        return;
      }

      showMessage('error', (ret && ret.message) ? ret.message : '上传失败');
      resetButton($btn, hadPhoto ? '更换' : '上传');
    }).fail(function (xhr) {
      var ret = xhr.responseJSON || {};
      var message = ret.message || ('上传失败（HTTP ' + xhr.status + '）');
      if (xhr.status === 419) {
        message = '页面已过期，请刷新后重新上传';
      }
      showMessage('error', message);
      resetButton($btn, hadPhoto ? '更换' : '上传');
    }).always(function () {
      $input.val('');
    });
  }

  $(document).off('click.orderFpUpload', '[data-order-fp-upload]').on('click.orderFpUpload', '[data-order-fp-upload]', function () {
    var target = $(this).data('target');
    if (target) {
      $(target).trigger('click');
    }
  });

  $(document).off('change.orderFpUpload', '[data-order-fp-input]').on('change.orderFpUpload', '[data-order-fp-input]', function () {
    if (!this.files || !this.files.length) {
      return;
    }

    uploadPhoto($(this).closest('form'));
  });
})();
