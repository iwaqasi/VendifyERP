<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\User::find(2); // coiffured_admin (business_id = 2)
$token = $user->createToken('test-booking')->accessToken;

$payload = [
    'customer_name' => 'Mr. Test Customer',
    'customer_phone' => '99998888',
    'contact_id' => 15,
    'service_name' => 'HAIRCUT LONG HAIR',
    'service_price' => 55.000,
    'service_duration_minutes' => 30,
    'services_detail' => [55.000],
    'appointment_start' => '2026-11-09T23:02:00.000Z',
    'staff_id' => 3,
    'location_id' => 2,
    'notes' => 'Test appointment notes',
];

$req = \Illuminate\Http\Request::create('/api/v1/saloon/appointments', 'POST', $payload);
$req->headers->set('Accept', 'application/json');
$req->headers->set('Authorization', 'Bearer ' . $token);
$res = $app->handle($req);

echo "Status: " . $res->getStatusCode() . "\n";
echo "Content: " . $res->getContent() . "\n";

