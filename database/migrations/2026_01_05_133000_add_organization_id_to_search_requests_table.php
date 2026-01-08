<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('search_requests', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('assigned_to')
                ->constrained('organizations')
                ->nullOnDelete();
        });

        DB::table('search_requests')
            ->join('users', 'search_requests.created_by', '=', 'users.id')
            ->update([
                'search_requests.organization_id' => DB::raw('users.organization_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('search_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
