<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete()
                ->after('is_admin');
        });

        $users = DB::table('users')
            ->whereNotNull('organization_name')
            ->orderBy('id')
            ->get([
                'id',
                'organization_name',
                'organization_phone',
                'organization_email',
                'organization_website',
                'organization_logo_path',
            ]);

        $organizationIds = [];

        foreach ($users as $user) {
            $name = $user->organization_name;
            if (! $name) {
                continue;
            }

            if (! array_key_exists($name, $organizationIds)) {
                $organizationIds[$name] = DB::table('organizations')->insertGetId([
                    'name' => $name,
                    'phone' => $user->organization_phone,
                    'email' => $user->organization_email,
                    'website' => $user->organization_website,
                    'logo_path' => $user->organization_logo_path,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update(['organization_id' => $organizationIds[$name]]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'organization_name',
                'organization_phone',
                'organization_email',
                'organization_website',
                'organization_logo_path',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('organization_name')->nullable()->after('is_admin');
            $table->string('organization_phone')->nullable()->after('organization_name');
            $table->string('organization_email')->nullable()->after('organization_phone');
            $table->string('organization_website')->nullable()->after('organization_email');
            $table->string('organization_logo_path')->nullable()->after('organization_website');
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::dropIfExists('organizations');
    }
};
