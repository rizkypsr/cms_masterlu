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
        Schema::create('community_playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_playlist_id')->constrained('community_playlists')->onDelete('cascade');
            $table->tinyInteger('type')->comment('1=audio, 2=video, 3=book, 4=topic1, 5=topic2');
            $table->unsignedBigInteger('item_id'); // ID of the audio/video/book/topic
            $table->json('data')->nullable(); // Flexible data storage for additional info
            $table->integer('seq')->default(0); // Order in playlist
            $table->timestamps();

            $table->index('community_playlist_id');
            $table->index(['type', 'item_id']);
            $table->index('seq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_playlist_items');
    }
};
