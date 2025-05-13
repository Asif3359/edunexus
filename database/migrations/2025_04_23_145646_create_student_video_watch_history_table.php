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
        Schema::create('student_video_watch_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->references('user_id')->on('users')->constrained('users')->onDelete('cascade');
            $table->foreignId('videos_id')->constrained('videos')->onDelete('cascade');
            $table->dateTime('watched_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_video_watch_history');
    }
};
