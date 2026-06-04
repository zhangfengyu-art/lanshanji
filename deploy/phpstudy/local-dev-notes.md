# 本机 phpstudy 环境备忘（Windows）

- **PHP**：`D:\EDGEdownload\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe`（项目需 7.3～7.4）
- **MySQL**：5.7.26，库名 `myshop_ab`，本机 root/root（仅开发）
- **Apache**：默认站点根目录 `phpstudy_pro/WWW`；本项目日常用 `artisan serve`，不依赖 Apache 虚拟主机

## 启动双站（PowerShell）

```powershell
cd C:\dev\myshop
$php = "D:\EDGEdownload\phpstudy_pro\Extensions\php\php7.3.4nts\php.exe"
$env:SITE_MODE = "A"
& $php artisan serve --host=127.0.0.1 --port=8000

# 另开终端 B 站
$env:SITE_MODE = "B"
& $php artisan serve --host=127.0.0.1 --port=8001
```

生产服务器（CloudCone）请使用 **Nginx + PHP-FPM**，不要照搬 phpstudy 的 Apache 配置。
