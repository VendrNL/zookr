<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('organization_name')->nullable()->after('is_admin');
            $table->string('organization_phone')->nullable()->after('organization_name');
            $table->string('organization_email')->nullable()->after('organization_phone');
            $table->string('organization_website')->nullable()->after('organization_email');
            $table->string('organization_logo_path')->nullable()->after('organization_website');
            $table->json('specialism_types')->nullable()->after('organization_logo_path');
            $table->json('specialism_provinces')->nullable()->after('specialism_types');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'organization_name',
                'organization_phone',
                'organization_email',
                'organization_website',
                'organization_logo_path',
                'specialism_types',
                'specialism_provinces',
            ]);
        });
    }
};
