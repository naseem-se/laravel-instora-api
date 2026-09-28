<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_ledger', function (Blueprint $table) {
            $table->unsignedBigInteger('installment_plan_id')->nullable()->after('customer_id');
            $table->index('installment_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_ledger', function (Blueprint $table) {
            $table->dropIndex(['installment_plan_id']);
            $table->dropColumn('installment_plan_id');
        });
    }
};
