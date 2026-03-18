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
        Schema::table('topics3_chapters', function (Blueprint $table) {
            $table->unsignedInteger('video_category_id')->nullable()->after('have_child');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topics3_chapters', function (Blueprint $table) {
            $table->dropColumn('video_category_id');
        });
    }
};
