<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\CustomerStatus;
use App\Exceptions\CustomerHasActiveRecordsException;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(array $data, CompanyContext $context, User $actor): Customer
    {
        $companyId = $context->requireCompanyId();

        return DB::transaction(function () use ($data, $companyId, $actor) {
            // Row lock serializes concurrent creates so two requests can never
            // compute the same next sequence number for this company.
            Company::where('id', $companyId)->lockForUpdate()->first();

            $customer = new Customer($data);
            $customer->company_id = $companyId;
            $customer->customer_number = $this->nextCustomerNumber($companyId);
            $customer->status = CustomerStatus::Active;
            $customer->created_by = $actor->id;
            $customer->save();

            $this->audit->log(
                AuditAction::CustomerCreated->value,
                entity: $customer,
                newValues: ['name' => $customer->name, 'customer_number' => $customer->customer_number],
                companyId: $companyId,
                userId: $actor->id,
            );

            return $customer;
        });
    }

    public function update(Customer $customer, array $data, User $actor): Customer
    {
        $original = $customer->only(['name', 'phone', 'email', 'status', 'city']);

        $customer->fill(collect($data)->except(['status'])->toArray());

        if (isset($data['status'])) {
            $customer->status = $data['status'];
        }

        $customer->save();

        $this->audit->log(
            AuditAction::CustomerUpdated->value,
            entity: $customer,
            oldValues: $original,
            newValues: $customer->only(array_keys($original)),
            companyId: $customer->company_id,
            userId: $actor->id,
        );

        return $customer;
    }

    public function delete(Customer $customer, User $actor): void
    {
        DB::transaction(function () use ($customer, $actor) {
            Company::where('id', $customer->company_id)->lockForUpdate()->first();
            $customer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $plans = $customer->installmentPlans()->lockForUpdate()->get();

            if ($plans->contains(fn ($plan) => $plan->status->value !== 'completed')) {
                throw new CustomerHasActiveRecordsException();
            }

            foreach ($plans as $plan) {
                $plan->delete();
                $this->audit->log(
                    AuditAction::InstallmentPlanDeleted->value,
                    entity: $plan,
                    oldValues: ['plan_number' => $plan->plan_number, 'status' => $plan->status->value],
                    companyId: $plan->company_id,
                    userId: $actor->id,
                );
            }

            $customer->delete();

            $this->audit->log(
                AuditAction::CustomerDeleted->value,
                entity: $customer,
                oldValues: ['customer_number' => $customer->customer_number, 'name' => $customer->name],
                companyId: $customer->company_id,
                userId: $actor->id,
            );
        });
    }

    private function nextCustomerNumber(int $companyId): string
    {
        $prefix = 'CUS-'.now()->year.'-';

        $count = Customer::withTrashed()
            ->where('company_id', $companyId)
            ->where('customer_number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}