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
        Schema::create('audio2_category', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->integer('seq')->default(0);
            $table->unsignedBigInteger('source_category_id')->nullable()->unique();
        });

        Schema::create('audio2', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audio2_category_id')->constrained('audio2_category')->cascadeOnDelete();
            $table->string('title');
            $table->string('url', 500)->nullable();
            $table->string('duration', 20)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
            $table->date('aired_on')->nullable();
            $table->integer('seq')->default(0);
            $table->unsignedBigInteger('source_audio_id')->nullable()->unique();

            $table->index(['audio2_category_id', 'year', 'month']);
            $table->index('aired_on');
        });

        Schema::create('audio2_segment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audio2_id')->constrained('audio2')->cascadeOnDelete();
            $table->unsignedSmallInteger('caller')->default(0);
            $table->string('code', 50)->nullable();
            $table->unsignedInteger('timestamp')->default(0);
            $table->string('title', 500)->nullable();
            $table->longText('content')->nullable();
            $table->integer('seq')->default(0);

            $table->index(['audio2_id', 'seq']);

            // The API search runs MATCH(title, content) AGAINST(...); SQLite (tests) has no FULLTEXT.
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->fullText(['title', 'content'], 'ft_audio2_segment');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audio2_segment');
        Schema::dropIfExists('audio2');
        Schema::dropIfExists('audio2_category');
    }
};
