<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('search_requests')
            ->where('status', 'in_behandeling')
            ->update(['status' => 'concept']);
    }

    public function down(): void
    {
        DB::table('search_requests')
            ->where('status', 'concept')
            ->update(['status' => 'in_behandeling']);
    }
};
