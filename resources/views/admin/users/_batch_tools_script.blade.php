(function () {
  var routes = {
    ban: '/admin/users/batch/ban',
    unban: '/admin/users/batch/unban',
    'reset-session': '/admin/users/batch/reset-session',
    'verify-email': '/admin/users/batch/verify-email'
  };

  $(document).on('click', '[data-user-batch]', function (e) {
    e.preventDefault();
    var action = $(this).data('user-batch');
    var url = routes[action];
    if (!url || !window.AdminBatch) {
      return;
    }
    var confirmMap = {
      ban: '确认封禁选中的用户？封禁后将强制退出登录。',
      unban: '确认解封选中的用户？',
      'reset-session': '确认重置选中用户的登录态？',
      'verify-email': '确认将选中用户标记为邮箱已验证？'
    };
    window.AdminBatch.post(url, {}, {
      emptyMsg: '请先勾选用户',
      confirm: confirmMap[action] || null
    });
  });
})();
