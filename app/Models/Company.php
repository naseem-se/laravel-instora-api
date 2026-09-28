<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string $currency
 * @property string $timezone
 * @property CompanyStatus $status
 */
class Company extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'status', 'suspension_reason'];

    protected function casts(): array
    {
        return [
            'status' => CompanyStatus::class,
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function installmentPlans(): HasMany
    {
        return $this->hasMany(InstallmentPlan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function cashAccounts(): HasMany
    {
        return $this->hasMany(CashAccount::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(CompanySetting::class);
    }

    public function whatsappProviders(): HasMany
    {
        return $this->hasMany(WhatsAppProvider::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(PlatformSubscription::class);
    }
}