<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $openingNotes = ['Opening stock', 'Opening balance imported from product stock'];

        DB::table('inventory_warehouses')
            ->where('name', 'Main Warehouse')
            ->orderBy('id')
            ->each(function (object $warehouse) use ($openingNotes): void {
                $hasOtherMovements = DB::table('inventory_movements')
                    ->where('warehouse_id', $warehouse->id)
                    ->where(function ($query) use ($openingNotes): void {
                        $query->whereNull('note')->orWhereNotIn('note', $openingNotes);
                    })
                    ->exists();

                if ($hasOtherMovements) {
                    return;
                }

                DB::table('inventory_movements')
                    ->where('warehouse_id', $warehouse->id)
                    ->whereIn('note', $openingNotes)
                    ->update(['warehouse_id' => null]);

                DB::table('inventory_warehouses')->where('id', $warehouse->id)->delete();
            });
    }

    public function down(): void
    {
        // Removed warehouses contained no location-specific movement history.
    }
};