<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$p = App\Models\WhatsAppProvider::find(2);
$res = Illuminate\Support\Facades\Http::withToken($p->credentials['access_token'])->get('https://graph.facebook.com/v25.0/' . $p->phone_number_id);
echo $res->body();
