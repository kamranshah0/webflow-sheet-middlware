<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;

if (Schema::hasTable('settings')) {
    echo "Table 'settings' exists.\n";
} else {
    echo "Table 'settings' does NOT exist.\n";
}
