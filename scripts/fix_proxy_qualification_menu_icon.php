<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$n = DB::table('admin_menu')
    ->where('uri', 'proxy-qualifications')
    ->update([
        'icon' => 'fa-shield',
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

echo "Updated {$n} menu row(s). Icon is now fa-shield.\n";
