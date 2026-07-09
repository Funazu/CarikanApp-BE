<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('item_id');
            $table->uuid('claimer_id');
            $table->text('proof_description');
            $table->string('proof_image_url')->nullable();
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->timestamps();

            // Foreign keys
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('claimer_id')->references('id')->on('users')->onDelete('cascade');

            // Unique constraint on item_id and claimer_id
            $table->unique(['item_id', 'claimer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
