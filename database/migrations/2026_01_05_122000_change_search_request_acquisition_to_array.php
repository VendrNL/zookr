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
            $table->json('acquisitions')->after('accessibility');
            $table->dropColumn('acquisition_type');
        });
    }

    public function down(): void
    {
        Schema::table('search_requests', function (Blueprint $table) {
            $table->string('acquisition_type', 50)->after('accessibility');
            $table->dropColumn('acquisitions');
        });
    }
};
