<?php

use App\Http\Controllers\Api\V1\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Api\V1\Admin\CompanyUserController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionController;
use App\Http\Controllers\Api\V1\Admin\WhatsAppAccessController;
use App\Http\Controllers\Api\V1\Admin\WhatsAppController as AdminWhatsAppController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\CompanyProfileController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerDocumentController;
use App\Http\Controllers\Api\V1\CustomerLedgerController;
use App\Http\Controllers\Api\V1\CustomerNoteController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InstallmentController;
use App\Http\Controllers\Api\V1\InstallmentPlanController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationTemplateController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProductCategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\EvolutionApiController;
use App\Http\Controllers\Api\V1\WhatsAppSettingsController;
use App\Http\Controllers\Api\V1\WhatsAppWebhookController;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\ResolveCompanyContext;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('health', HealthController::class)->name('health');

    // EXACT FIX: Removed redundant 'web' middleware from auth/login
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::post('auth/forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:password-reset')
        ->name('auth.forgot-password');
    Route::post('auth/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:password-reset')
        ->name('auth.reset-password');

    Route::prefix('webhooks/whatsapp/meta')
        ->name('webhooks.whatsapp.meta.')
        ->middleware('throttle:whatsapp-webhook')
        ->group(function () {
            Route::get('/', [WhatsAppWebhookController::class, 'verifyMeta'])->name('verify');
            Route::post('/', [WhatsAppWebhookController::class, 'handleMeta'])->name('handle');
        });

    Route::prefix('webhooks/whatsapp/{provider}')
        ->name('webhooks.whatsapp.')
        ->middleware('throttle:whatsapp-webhook')
        ->group(function () {
            Route::get('/', [WhatsAppWebhookController::class, 'verify'])->name('verify');
            Route::post('/', [WhatsAppWebhookController::class, 'handle'])->name('handle');
        });

    Route::middleware(['auth:sanctum', ResolveCompanyContext::class])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        
        // EXACT FIX: Removed redundant 'web' middleware from auth/logout
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('company', [CompanyProfileController::class, 'show'])->name('company.show');
        Route::put('company', [CompanyProfileController::class, 'update'])->name('company.update');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');

        Route::apiResource('users', UserController::class)
            ->except(['destroy'])
            ->parameters(['users' => 'id']);

        Route::apiResource('customers', CustomerController::class)
            ->parameters(['customers' => 'id']);

        Route::get('customers/{customer}/notes', [CustomerNoteController::class, 'index'])
            ->name('customers.notes.index');
        Route::post('customers/{customer}/notes', [CustomerNoteController::class, 'store'])
            ->name('customers.notes.store');
        Route::get('customers/{customerId}/documents', [CustomerDocumentController::class, 'index'])
            ->name('customers.documents.index');
        Route::post('customers/{customerId}/documents', [CustomerDocumentController::class, 'store'])
            ->name('customers.documents.store');
        Route::get('customers/{customerId}/documents/{documentId}/download', [CustomerDocumentController::class, 'download'])
            ->name('customers.documents.download');
        Route::delete('customers/{customerId}/documents/{documentId}', [CustomerDocumentController::class, 'destroy'])
            ->name('customers.documents.destroy');
        Route::get('customers/{customer}/ledger', [CustomerLedgerController::class, 'index'])
            ->name('customers.ledger.index');

        Route::apiResource('product-categories', ProductCategoryController::class)
            ->parameters(['product-categories' => 'id']);

        Route::apiResource('products', ProductController::class)
            ->parameters(['products' => 'id']);

        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/history', [InventoryController::class, 'history'])->name('inventory.history');
        Route::post('inventory/movements', [InventoryController::class, 'storeMovement'])->name('inventory.movements.store');
        Route::post('inventory/warehouses', [InventoryController::class, 'storeWarehouse'])->name('inventory.warehouses.store');
        Route::put('inventory/warehouses/{id}', [InventoryController::class, 'updateWarehouse'])->name('inventory.warehouses.update');
        Route::delete('inventory/warehouses/{id}', [InventoryController::class, 'destroyWarehouse'])->name('inventory.warehouses.destroy');

        Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
        Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
        Route::get('sales/{id}', [SaleController::class, 'show'])->name('sales.show');
        Route::post('sales/{id}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
        Route::post('sales/{id}/return', [SaleController::class, 'return'])->name('sales.return');
        Route::get('sales/{id}/invoice', [SaleController::class, 'invoice'])->name('sales.invoice');
        Route::get('sales/{id}/agreement', [SaleController::class, 'agreement'])->name('sales.agreement');

        Route::apiResource('installment-plans', InstallmentPlanController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->parameters(['installment-plans' => 'id']);

        Route::post('installment-plans/{id}/approve', [InstallmentPlanController::class, 'approve'])
            ->name('installment-plans.approve');
        Route::post('installment-plans/{id}/cancel', [InstallmentPlanController::class, 'cancel'])
            ->name('installment-plans.cancel');
        Route::post('installment-plans/{id}/settle', [InstallmentPlanController::class, 'settle'])
            ->name('installment-plans.settle');
        Route::get('installment-plans/{id}/invoice', [InstallmentPlanController::class, 'invoicePdf'])
            ->name('installment-plans.invoice');
        Route::get('installment-plans/{id}/statement', [InstallmentPlanController::class, 'statementPdf'])
            ->name('installment-plans.statement');

        Route::post('installments/{id}/waive-late-fee', [InstallmentController::class, 'waiveLateFee'])
            ->name('installments.waive-late-fee');

        Route::apiResource('payments', PaymentController::class)
            ->only(['index', 'store', 'show'])
            ->parameters(['payments' => 'id']);

        Route::post('payments/{id}/reverse', [PaymentController::class, 'reverse'])
            ->name('payments.reverse');
        Route::get('payments/{id}/receipt', [PaymentController::class, 'receiptPdf'])
            ->name('payments.receipt');

        Route::apiResource('notifications', NotificationController::class)
            ->only(['index', 'show'])
            ->parameters(['notifications' => 'id']);
        Route::post('notifications/{id}/retry', [NotificationController::class, 'retry'])
            ->middleware('throttle:notification-retry')
            ->name('notifications.retry');

        Route::get('notification-templates', [NotificationTemplateController::class, 'index'])
            ->name('notification-templates.index');
        Route::put('notification-templates', [NotificationTemplateController::class, 'update'])
            ->name('notification-templates.update');

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('whatsapp', [WhatsAppSettingsController::class, 'show'])->name('whatsapp.show');
            Route::delete('whatsapp', [WhatsAppSettingsController::class, 'destroy'])->name('whatsapp.destroy');
            Route::post('whatsapp/test-connection', [WhatsAppSettingsController::class, 'testConnection'])
                ->name('whatsapp.test-connection');
            Route::post('whatsapp/embedded-signup', [WhatsAppSettingsController::class, 'completeEmbeddedSignup'])
                ->middleware('throttle:whatsapp-embedded-signup')
                ->name('whatsapp.embedded-signup');
            Route::post('whatsapp/test-message', [WhatsAppSettingsController::class, 'testMessage'])
                ->middleware('throttle:whatsapp-test')
                ->name('whatsapp.test-message');

            // Evolution API — QR code session routes
            Route::post('whatsapp/evolution/init', [EvolutionApiController::class, 'initSession'])
                ->name('whatsapp.evolution.init');
            Route::get('whatsapp/evolution/status', [EvolutionApiController::class, 'sessionStatus'])
                ->name('whatsapp.evolution.status');
            Route::delete('whatsapp/evolution/disconnect', [EvolutionApiController::class, 'disconnect'])
                ->name('whatsapp.evolution.disconnect');
        });

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('dashboard', [ReportController::class, 'dashboard'])->name('dashboard');
            Route::get('aging', [ReportController::class, 'agingSummary'])->name('aging');
            Route::get('aging/installments', [ReportController::class, 'agingInstallments'])->name('aging.installments');
            Route::get('payment-methods', [ReportController::class, 'paymentMethods'])->name('payment-methods');
        });

        Route::middleware(EnsureSuperAdmin::class)->prefix('admin')->name('admin.')->group(function () {
            Route::apiResource('companies', AdminCompanyController::class)
                ->parameters(['companies' => 'id']);

            Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');
            Route::post('companies/{companyId}/users', [CompanyUserController::class, 'store'])->name('companies.users.store');

            Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
                Route::get('/', [SubscriptionController::class, 'index'])->name('index');
                Route::post('companies/{companyId}', [SubscriptionController::class, 'store'])->name('store');
                Route::get('companies/{companyId}', [SubscriptionController::class, 'show'])->name('show');
                Route::put('companies/{companyId}', [SubscriptionController::class, 'update'])->name('update');
                Route::post('invoices/{invoiceId}/payments', [SubscriptionController::class, 'recordPayment'])->name('payments.store');
            });

            Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
                Route::get('/', [AdminWhatsAppController::class, 'show'])->name('show');
                Route::post('/', [AdminWhatsAppController::class, 'store'])->name('store');
                Route::delete('/', [AdminWhatsAppController::class, 'destroy'])->name('destroy');
                Route::post('test-connection', [AdminWhatsAppController::class, 'testConnection'])->name('test-connection');
                Route::post('test-message', [AdminWhatsAppController::class, 'testMessage'])
                    ->middleware('throttle:whatsapp-test')
                    ->name('test-message');

                Route::get('access', [WhatsAppAccessController::class, 'index'])->name('access.index');
                Route::put('access/{company}', [WhatsAppAccessController::class, 'updateCompanyAccess'])->name('access.update-company');
                Route::get('access/{company}/users', [WhatsAppAccessController::class, 'indexUsers'])->name('access.index-users');
                Route::put('access/{company}/users/{user}', [WhatsAppAccessController::class, 'updateUserAccess'])->name('access.update-user');
            });
        });
    });
});