<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Encore\Admin\Auth\Database\Administrator;
use Encore\Admin\Auth\Database\Role;
use Illuminate\Support\Str;

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? 'admin123456';
$name = $argv[3] ?? '超级管理员';

$user = Administrator::query()->where('username', $username)->first();

if ($user) {
    $user->password = bcrypt($password);
    $user->name = $name;
    $user->save();
    $action = 'updated';
} else {
    $user = new Administrator();
    $user->username = $username;
    $user->password = bcrypt($password);
    $user->name = $name;
    $user->remember_token = Str::random(60);
    $user->save();
    $action = 'created';
}

$role = Role::query()->where('slug', 'administrator')->first();
if ($role) {
    $user->roles()->sync([$role->id]);
} else {
    fwrite(STDERR, "Warning: no administrator role. Run: php scripts/fix_admin_rbac.php\n");
}

echo json_encode([
    'action' => $action,
    'username' => $username,
    'password' => $password,
    'name' => $name,
    'login_url' => 'http://127.0.0.1:8000/admin/auth/login',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
