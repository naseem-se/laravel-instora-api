<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')->orderBy('id')->each(function (object $company) {
            DB::table('products')
                ->where('company_id', $company->id)
                ->where('stock_quantity', '>', 0)
                ->orderBy('id')
                ->each(function (object $product) use ($company) {
                    DB::table('inventory_movements')->insert([
                        'company_id' => $company->id,
                        'product_id' => $product->id,
                        'warehouse_id' => null,
                        'type' => 'in',
                        'direction' => 'in',
                        'quantity' => $product->stock_quantity,
                        'unit_cost' => $product->cost_price,
                        'note' => 'Opening balance imported from product stock',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
        });
    }

    public function down(): void
    {
        DB::table('inventory_movements')
            ->where('note', 'Opening balance imported from product stock')
            ->delete();
    }
};