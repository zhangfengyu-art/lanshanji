(function () {
  var ADMIN_BASE = '{{ admin_base_path() }}';
  var routes = {
    'mark-handled': ADMIN_BASE + '/support-feedbacks/batch/mark-handled',
    'mark-pending': ADMIN_BASE + '/support-feedbacks/batch/mark-pending',
    reply: ADMIN_BASE + '/support-feedbacks/batch/reply'
  };

  $(document).on('click', '[data-feedback-batch]', function (e) {
    e.preventDefault();
    var action = $(this).data('feedback-batch');
    var url = routes[action];
    if (!url || !window.AdminBatch) {
      return;
    }
    if (action === 'reply') {
      var reply = $('.batch-feedback-reply').val();
      if (!reply || !String(reply).trim()) {
        alert('请填写统一回复内容');
        return;
      }
      window.AdminBatch.post(url, { admin_reply: reply }, {
        emptyMsg: '请先勾选反馈',
        confirm: '确认用该内容批量回复并结案？'
      });
      return;
    }
    var confirmMap = {
      'mark-handled': '确认将选中反馈标为已回复？（不修改已有回复文案）',
      'mark-pending': '确认将选中反馈改回待处理？将清空回复与处理人信息。'
    };
    window.AdminBatch.post(url, {}, {
      emptyMsg: '请先勾选反馈',
      confirm: confirmMap[action] || null
    });
  });
})();
