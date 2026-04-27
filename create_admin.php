<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\App\Models\User::firstOrCreate(
    ['email' => 'admin@example.com'],
    ['name' => 'Admin', 'password' => bcrypt('password')]
);
echo "Admin user created!\n";
