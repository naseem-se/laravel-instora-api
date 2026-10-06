<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SalesLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_cash_sale_creates_invoice_and_payment_then_return_restores_stock(): void
    {
        [$company, $user, $customer] = $this->makeTenant();
        $product = Product::factory()->for($company)->create([
            'cost_price' => 500,
            'cash_price' => 1000,
            'stock_quantity' => 5,
        ]);
        InventoryMovement::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'warehouse_id' => null,
            'type' => 'in',
            'direction' => 'in',
            'quantity' => 5,
            'unit_cost' => 500,
            'note' => 'Opening stock',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/sales', [
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'discount' => 100,
            'additional_charges' => 25,
            'tax' => 10,
        ])->assertCreated();

        $sale = Sale::query()->with('invoice')->findOrFail($response->json('data.id'));
        $this->assertSame('1935.00', $sale->total_amount);
        $this->assertNotNull($sale->invoice_id);
        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'amount' => '1935.00',
            'status' => 'completed',
        ]);
        $this->assertSame('3.000', $product->fresh()->stock_quantity);

        $this->actingAs($user)->postJson("/api/v1/sales/{$sale->id}/return", ['reason' => 'Customer returned item'])
            ->assertOk()
            ->assertJsonPath('data.status', 'returned');

        $this->assertSame('5.000', $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('payments', ['sale_id' => $sale->id, 'status' => 'reversed']);
        $this->assertDatabaseHas('sale_returns', ['sale_id' => $sale->id, 'type' => 'return']);
    }

    public function test_sale_can_create_a_one_time_buyer_without_a_phone_number(): void
    {
        [$company, $user] = $this->makeTenant();
        $product = Product::factory()->for($company)->create([
            'cash_price' => 2500,
            'stock_quantity' => null,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/sales', [
            'new_customer' => [
                'name' => 'Walk-in Buyer',
                'email' => 'buyer@example.test',
            ],
            'product_id' => $product->id,
            'quantity' => 1,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
        ])->assertCreated();

        $sale = Sale::query()->with('customer')->findOrFail($response->json('data.id'));
        $this->assertSame('Walk-in Buyer', $sale->customer->name);
        $this->assertNull($sale->customer->phone);
        $this->assertSame('buyer@example.test', $sale->customer->email);
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
}