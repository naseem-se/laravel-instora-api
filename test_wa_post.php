<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$p = App\Models\WhatsAppProvider::find(2);
$payload = [
    'messaging_product' => 'whatsapp',
    'to' => '923413855585',
    'type' => 'text',
    'text' => ['body' => 'Test message via script'],
];
$res = Illuminate\Support\Facades\Http::withToken($p->credentials['access_token'])
    ->post('https://graph.facebook.com/v25.0/' . $p->phone_number_id . '/messages', $payload);
echo "Status: " . $res->status() . "\n";
echo "Body: " . $res->body() . "\n";
