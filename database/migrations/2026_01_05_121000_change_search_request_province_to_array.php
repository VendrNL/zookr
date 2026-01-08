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
            $table->json('provinces')->after('location');
            $table->dropColumn('province');
        });
    }

    public function down(): void
    {
        Schema::table('search_requests', function (Blueprint $table) {
            $table->string('province', 50)->after('location');
            $table->dropColumn('provinces');
        });
    }
};
