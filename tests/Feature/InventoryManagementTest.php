<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\InventoryWarehouse;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_stock_in_and_transfer_keep_balances_and_history_company_scoped(): void
    {
        $company = Company::factory()->create();
        $user = $this->makeCompanyAdmin($company);
        $product = Product::factory()->for($company)->create(['reorder_level' => 2]);
        $from = InventoryWarehouse::create(['company_id' => $company->id, 'name' => 'Main']);
        $to = InventoryWarehouse::create(['company_id' => $company->id, 'name' => 'Branch']);

        $this->actingAs($user)->postJson('/api/v1/inventory/movements', [
            'product_id' => $product->id,
            'warehouse_id' => $from->id,
            'type' => 'in',
            'quantity' => 5,
            'unit_cost' => 1200,
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/inventory/movements', [
            'product_id' => $product->id,
            'warehouse_id' => $from->id,
            'destination_warehouse_id' => $to->id,
            'type' => 'transfer',
            'quantity' => 2,
        ])->assertCreated();

        $this->assertDatabaseCount('inventory_movements', 3);
        $this->assertEquals(3, $this->balance($company->id, $product->id, $from->id));
        $this->assertEquals(2, $this->balance($company->id, $product->id, $to->id));

        $this->actingAs($user)->getJson('/api/v1/inventory')
            ->assertOk()
            ->assertJsonPath('data.products.0.on_hand', 5)
            ->assertJsonPath('data.products.0.stock_value', round((float) $product->cost_price * 5, 2));
    }

    public function test_inventory_rejects_products_from_another_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = $this->makeCompanyAdmin($company);
        $foreignProduct = Product::factory()->for($otherCompany)->create();
        $warehouse = InventoryWarehouse::create(['company_id' => $company->id, 'name' => 'Main']);

        $this->actingAs($user)->postJson('/api/v1/inventory/movements', [
            'product_id' => $foreignProduct->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'in',
            'quantity' => 1,
        ])->assertUnprocessable();

        $this->assertSame(0, InventoryMovement::count());
    }

    private function makeCompanyAdmin(Company $company): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $role = Role::firstOrCreate(['name' => 'Company Admin', 'guard_name' => 'web', 'company_id' => $company->id]);
        $role->syncPermissions(PermissionSeeder::companyAdminDefaults());
        $user = User::factory()->for($company)->create();
        $user->assignRole($role);

        return $user;
    }

    private function balance(int $companyId, int $productId, int $warehouseId): float
    {
        return (float) InventoryMovement::query()
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) as balance")
            ->value('balance');
    }
}