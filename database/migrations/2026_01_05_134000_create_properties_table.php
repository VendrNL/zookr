<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('search_request_id')
                ->nullable()
                ->constrained('search_requests')
                ->nullOnDelete();

            $table->string('name', 150)->nullable();
            $table->string('address', 150);
            $table->string('city', 120);
            $table->string('surface_area', 150);
            $table->string('parking_spots', 150)->nullable();
            $table->string('availability', 150);
            $table->decimal('rent_price', 12, 2)->nullable();
            $table->decimal('asking_price', 12, 2)->nullable();
            $table->json('images')->nullable();
            $table->json('drawings')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
