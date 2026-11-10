<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\User::where('username', 'superadmin')->first();
$token = $user->createToken('test-device')->accessToken;

$request = \Illuminate\Http\Request::create('/api/v1/business-type', 'GET');
$request->headers->set('Accept', 'application/json');
$request->headers->set('Authorization', 'Bearer ' . $token);

$response = $app->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";

