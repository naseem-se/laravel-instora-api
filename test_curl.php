<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$p = App\Models\WhatsAppProvider::find(2);
$cmd = 'curl.exe -s -X POST https://graph.facebook.com/v20.0/1326802470517551/messages -H "Authorization: Bearer ' . $p->credentials['access_token'] . '" -H "Content-Type: application/json" -d "{\"messaging_product\":\"whatsapp\"}"';
echo shell_exec($cmd);
