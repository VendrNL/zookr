<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scrape_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255);
            $table->string('property_field', 100);
            $table->string('selector', 2048)->nullable();
            $table->timestamps();

            $table->unique(['domain', 'property_field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrape_mappings');
    }
};
