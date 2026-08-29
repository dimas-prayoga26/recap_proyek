<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('work_items', 'project_area_id')) {
            Schema::table('work_items', function (Blueprint $table): void {
                $table->dropIndex('work_items_project_area_name_index');
                $table->dropConstrainedForeignId('project_area_id');
            });
        }

        if (Schema::hasColumn('project_transactions', 'project_area_id')) {
            Schema::table('project_transactions', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('project_area_id');
            });
        }

        if (Schema::hasColumn('project_offers', 'project_area_id')) {
            Schema::table('project_offers', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('project_area_id');
            });
        }

        if (Schema::hasColumn('project_offers', 'area')) {
            Schema::table('project_offers', function (Blueprint $table): void {
                $table->dropIndex(['project_name', 'area']);
                $table->dropColumn('area');
            });
        }

        Schema::dropIfExists('project_areas');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('project_areas')) {
            Schema::create('project_areas', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->string('code', 50);
                $table->string('name');
                $table->timestamps();

                $table->unique(['project_id', 'code']);
            });
        }

        if (! Schema::hasColumn('work_items', 'project_area_id')) {
            Schema::table('work_items', function (Blueprint $table): void {
                $table->foreignId('project_area_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
                $table->index(['project_id', 'project_area_id', 'name'], 'work_items_project_area_name_index');
            });
        }

        if (! Schema::hasColumn('project_transactions', 'project_area_id')) {
            Schema::table('project_transactions', function (Blueprint $table): void {
                $table->foreignId('project_area_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('project_offers', 'project_area_id')) {
            Schema::table('project_offers', function (Blueprint $table): void {
                $table->foreignId('project_area_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('project_offers', 'area')) {
            Schema::table('project_offers', function (Blueprint $table): void {
                $table->string('area', 20)->default('')->after('project_name');
                $table->index(['project_name', 'area']);
            });
        }
    }
};
