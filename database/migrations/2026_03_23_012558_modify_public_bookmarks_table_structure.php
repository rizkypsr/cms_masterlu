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
        Schema::table('public_bookmarks', function (Blueprint $table) {
            $table->dropColumn('bookmark_id');
            $table->string('title');
            $table->integer('pengguna_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public_bookmarks', function (Blueprint $table) {
            $table->dropColumn(['title', 'pengguna_id']);
            $table->integer('bookmark_id')->nullable()->index();
        });
    }
};
