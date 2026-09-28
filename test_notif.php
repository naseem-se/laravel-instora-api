<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $plan = App\Models\InstallmentPlan::find(1);
    if (!$plan) { echo "No plan found.\n"; exit; }
    $invoice = App\Models\Invoice::first();
    
    // Simulate NotificationService::send
    $customer = App\Models\Customer::find($plan->customer_id);
    $company = App\Models\Company::find($plan->company_id);
    
    $decision = app(App\Services\Notification\NotificationPreferenceResolver::class)->resolve($customer->id);
    echo "Pref: Email enabled? " . ($decision->channelEnabled(App\Enums\NotificationChannel::Email) ? 'Yes' : 'No') . "\n";
    
    $template = app(App\Services\Notification\NotificationTemplateResolver::class)->resolve(
        $plan->company_id, 
        App\Enums\NotificationType::PlanApproved, 
        App\Enums\NotificationChannel::Email
    );
    
    if (!$template) {
        echo "No template found.\n";
    } else {
        echo "Template found: " . $template->id . "\n";
        $renderer = app(App\Support\TemplateRenderer::class);
        $subject = $renderer->render($template->subject, ['customer_name' => $customer->name, 'company_name' => 'Test', 'remaining_balance' => '100']);
        echo "Subject: " . $subject . "\n";
    }
} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile() . "\n";
}
