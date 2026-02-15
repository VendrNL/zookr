<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_request_mailings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_request_id')
                ->constrained('search_requests')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('office_name', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['search_request_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_request_mailings');
    }
};

