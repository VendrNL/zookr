<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('search_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('location')->nullable();      // bijv. regio/stad
            $table->integer('budget_min')->nullable();   // in euro's
            $table->integer('budget_max')->nullable();

            $table->date('due_date')->nullable();

            $table->string('status')->default('open');   // open|in_behandeling|afgerond|geannuleerd

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_requests');
    }
};
