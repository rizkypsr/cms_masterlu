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
        Schema::create('book_video', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('book_chapters_id');
            $table->unsignedInteger('video_category_id');
            $table->unsignedInteger('seq')->nullable();

            $table->index('book_chapters_id');
            $table->index('video_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_video');
    }
};
