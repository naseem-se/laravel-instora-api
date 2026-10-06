<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CompletedRecordDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_completed_plan_can_be_soft_deleted_and_keeps_its_row(): void
    {
        [$company, $user, $customer] = $this->makeTenant();
        $planId = $this->makePlan($company, $customer, 'completed');

        $this->actingAs($user)->deleteJson("/api/v1/installment-plans/{$planId}")
            ->assertOk();

        $this->assertNotNull(InstallmentPlan::withTrashed()->findOrFail($planId)->deleted_at);
        $this->assertDatabaseHas('installment_plans', ['id' => $planId]);
    }

    public function test_customer_delete_soft_deletes_only_when_all_plans_are_completed(): void
    {
        [$company, $user, $customer] = $this->makeTenant();
        $completedPlanId = $this->makePlan($company, $customer, 'completed');

        $this->actingAs($user)->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertOk();

        $this->assertNotNull(Customer::withTrashed()->findOrFail($customer->id)->deleted_at);
        $this->assertNotNull(InstallmentPlan::withTrashed()->findOrFail($completedPlanId)->deleted_at);
        $this->assertDatabaseHas('installment_plans', ['id' => $completedPlanId]);
    }

    public function test_customer_with_an_incomplete_plan_cannot_be_deleted(): void
    {
        [$company, $user, $customer] = $this->makeTenant();
        $planId = $this->makePlan($company, $customer, 'active');

        $this->actingAs($user)->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'CUSTOMER_HAS_ACTIVE_RECORDS');

        $this->assertNull(Customer::withTrashed()->findOrFail($customer->id)->deleted_at);
        $this->assertNull(InstallmentPlan::findOrFail($planId)->deleted_at);
    }

    private function makeTenant(): array
    {
        $company = Company::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $role = Role::firstOrCreate(['name' => 'Company Admin', 'guard_name' => 'web']);
        $role->syncPermissions(PermissionSeeder::companyAdminDefaults());
        $user = User::factory()->for($company)->create();
        $user->assignRole($role);
        $customer = Customer::factory()->for($company)->create(['created_by' => $user->id]);

        return [$company, $user, $customer];
    }

    private function makePlan(Company $company, Customer $customer, string $status): int
    {
        return DB::table('installment_plans')->insertGetId([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'plan_number' => 'PLAN-'.strtoupper(bin2hex(random_bytes(5))),
            'start_date' => now()->toDateString(),
            'principal_amount' => 10000,
            'down_payment' => 0,
            'financed_amount' => 10000,
            'financial_charge_type' => 'none',
            'interest_amount' => 0,
            'late_fee_type' => 'none',
            'total_amount' => 10000,
            'paid_amount' => 0,
            'remaining_amount' => $status === 'completed' ? 0 : 10000,
            'installment_frequency' => 'monthly',
            'number_of_installments' => 10,
            'installment_amount' => 1000,
            'status' => $status,
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}