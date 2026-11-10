<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = \Illuminate\Http\Request::create('/api/v1/auth/login', 'POST', [
    'email' => 'superadmin',
    'password' => 'System32',
]);

$response = $app->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";
