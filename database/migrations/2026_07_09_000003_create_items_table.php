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
        Schema::create('items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description');
            $table->string('secret_feature');
            $table->string('image_url');
            $table->enum('type', ['LOST', 'FOUND']);
            $table->enum('status', ['OPEN', 'IN_PROCESS', 'RETURNED'])->default('OPEN');
            $table->dateTime('event_time');
            $table->string('contact_info');
            $table->string('storage_location')->nullable();
            
            $table->uuid('user_id');
            $table->uuid('category_id');
            $table->uuid('location_id');
            
            $table->timestamps();
            $table->softDeletes(); // deleted_at column

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('restrict');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
