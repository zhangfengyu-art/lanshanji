<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>后台请求失败</title>
  <style>
    body { font-family: "Segoe UI", "Microsoft YaHei", sans-serif; background: #f4f6f8; margin: 0; padding: 40px 16px; color: #333; }
    .box { max-width: 640px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 28px 32px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
    h1 { margin: 0 0 12px; font-size: 20px; color: #c0392b; }
    p { margin: 0 0 16px; line-height: 1.7; }
    .msg { background: #fff5f5; border: 1px solid #f5c6cb; padding: 12px 14px; border-radius: 6px; font-size: 14px; }
    a.btn { display: inline-block; margin-top: 8px; padding: 8px 16px; background: #3c8dbc; color: #fff; text-decoration: none; border-radius: 4px; }
    a.btn:hover { background: #367fa9; }
  </style>
</head>
<body>
  <div class="box">
    <h1>后台页面加载失败</h1>
    <p>请求未成功完成，请返回后台首页重试。若反复出现，请查看服务器日志 <code>storage/logs/laravel.log</code>。</p>
    <div class="msg">{{ $message ?? '未知错误' }}</div>
    <p style="margin-top: 20px;">
      <a class="btn" href="{{ $homeUrl ?? admin_url('/') }}">返回后台首页</a>
    </p>
  </div>
</body>
</html>
