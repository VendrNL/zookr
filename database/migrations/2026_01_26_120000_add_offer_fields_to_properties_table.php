<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('contact_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('acquisition', 20)
                ->nullable()
                ->after('availability');

            $table->text('notes')
                ->nullable()
                ->after('acquisition');

            $table->decimal('rent_price_per_m2', 12, 2)
                ->nullable()
                ->after('rent_price');

            $table->decimal('rent_price_parking', 12, 2)
                ->nullable()
                ->after('rent_price_per_m2');

            $table->string('brochure_path', 255)
                ->nullable()
                ->after('images');

            $table->string('url', 2048)
                ->nullable()
                ->after('drawings');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_user_id');
            $table->dropColumn([
                'acquisition',
                'notes',
                'rent_price_per_m2',
                'rent_price_parking',
                'brochure_path',
                'url',
            ]);
        });
    }
};
