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
        Schema::create('public_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->integer('bookmark_id')->nullable(); // Reference to the original bookmark
            $table->integer('seq')->default(0); // Order/sequence
            $table->boolean('is_active')->default(true);
            $table->boolean('is_pinned')->default(false); // Admin can pin bookmarks
            $table->timestamps();
            
            $table->index('bookmark_id');
            $table->index('seq');
            $table->index('is_pinned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_bookmarks');
    }
};
