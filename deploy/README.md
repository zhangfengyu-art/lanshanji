# 部署与数据迁移（CloudCone / 生产）

## 1. 克隆代码

```bash
git clone https://github.com/zhangfengyu-art/lanshanji.git myshop
cd myshop
composer install --no-dev --optimize-autoloader
cp .env.example .env
# 编辑 .env：APP_KEY、数据库、SITE_MODE、SITE_A_URL、SITE_B_URL、邮件与支付
php artisan key:generate   # 仅首次；若与 B 站共用 APP_KEY 则从美国站 .env 复制，不要重复生成
php artisan migrate --force
chmod -R 775 storage bootstrap/cache
```

## 2. 恢复 MySQL 数据（本机 phpstudy 导出）

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS myshop_ab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p myshop_ab < deploy/database/myshop_ab_20260524.sql
php artisan config:cache
```

Windows 本地导出命令（phpstudy）：

```powershell
& "D:\EDGEdownload\phpstudy_pro\Extensions\MySQL5.7.26\bin\mysqldump.exe" --host=127.0.0.1 -uroot -proot --single-transaction myshop_ab > deploy\database\myshop_ab_YYYYMMDD.sql
```

## 3. 上传文件（商品图等）

若 `storage/app/public` 里有图片，需单独打包上传到服务器同路径，并执行：

```bash
php artisan storage:link
```

## 4. 本地 phpstudy 说明

- 开发常用：`php artisan serve`（A 站 8000 / B 站 8001），不一定走 Apache。
- `deploy/phpstudy/` 仅作本机环境参考，**生产请用 Nginx + PHP-FPM**（或 aaPanel）。

## 5. 安全提醒

- **切勿**把 `.env` 提交到 Git（已在 .gitignore）。
- SQL 备份含用户与后台数据，仓库请设为 **Private**。
- 上线后请修改数据库密码、管理员密码，并重新配置支付密钥。
