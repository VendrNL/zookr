<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::table('search_requests')->delete();

        Schema::table('search_requests', function (Blueprint $table) {
            $table->string('customer_name', 150)->after('title');
            $table->string('province', 50)->after('location');
            $table->string('property_type', 50)->after('province');
            $table->string('surface_area', 150)->nullable()->after('property_type');
            $table->string('parking', 150)->nullable()->after('surface_area');
            $table->string('availability', 150)->nullable()->after('parking');
            $table->string('accessibility', 150)->nullable()->after('availability');
            $table->string('acquisition_type', 50)->after('accessibility');
            $table->text('notes')->nullable()->after('acquisition_type');

            $table->dropColumn([
                'description',
                'budget_min',
                'budget_max',
                'due_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('search_requests', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->integer('budget_min')->nullable()->after('location');
            $table->integer('budget_max')->nullable()->after('budget_min');
            $table->date('due_date')->nullable()->after('budget_max');

            $table->dropColumn([
                'customer_name',
                'province',
                'property_type',
                'surface_area',
                'parking',
                'availability',
                'accessibility',
                'acquisition_type',
                'notes',
            ]);
        });
    }
};
