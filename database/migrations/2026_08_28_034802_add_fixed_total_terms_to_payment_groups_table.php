<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_groups', function (Blueprint $table) {
            $table->unsignedSmallInteger('fixed_total_terms')->nullable()->after('total_terms');
        });

        DB::table('payment_groups')->update([
            'fixed_total_terms' => DB::raw('total_terms'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_groups', function (Blueprint $table) {
            $table->dropColumn('fixed_total_terms');
        });
    }
};
