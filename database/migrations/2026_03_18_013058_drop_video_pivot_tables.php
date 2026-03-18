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
        Schema::dropIfExists('book_video');
        Schema::dropIfExists('topic2_video');
        Schema::dropIfExists('topic3_video');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       
    }
};
