<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function makeCompanyAdmin(Company $company): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        $role = Role::firstOrCreate(['name' => 'Company Admin', 'guard_name' => 'web']);
        $role->givePermissionTo(PermissionSeeder::companyAdminDefaults());

        $user = User::factory()->for($company)->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_company_a_cannot_view_company_b_user(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $adminA = $this->makeCompanyAdmin($companyA);
        $userB = User::factory()->for($companyB)->create();

        $this->actingAs($adminA)
            ->getJson("/api/v1/users/{$userB->id}")
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_company_a_cannot_update_company_b_user(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $adminA = $this->makeCompanyAdmin($companyA);
        $userB = User::factory()->for($companyB)->create();

        $this->actingAs($adminA)
            ->putJson("/api/v1/users/{$userB->id}", ['name' => 'Hijacked'])
            ->assertStatus(404);

        $this->assertDatabaseHas('users', ['id' => $userB->id, 'name' => $userB->name]);
    }

    public function test_company_admin_can_only_list_their_own_companys_users(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $adminA = $this->makeCompanyAdmin($companyA);
        User::factory()->for($companyA)->count(2)->create();
        User::factory()->for($companyB)->count(3)->create();

        $response = $this->actingAs($adminA)->getJson('/api/v1/users');

        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('company_id')->unique();

        $this->assertEquals([$companyA->id], $ids->all());
    }

    public function test_non_super_admin_cannot_access_admin_company_routes(): void
    {
        $company = Company::factory()->create();
        $admin = $this->makeCompanyAdmin($company);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/companies')
            ->assertStatus(403)
            ->assertJsonPath('code', 'AUTHORIZATION_ERROR');
    }

    public function test_super_admin_can_access_admin_company_routes(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web', 'company_id' => null]);
        $superAdmin->assignRole($role);

        Company::factory()->count(2)->create();

        $this->actingAs($superAdmin)
            ->getJson('/api/v1/admin/companies')
            ->assertOk();
    }
}