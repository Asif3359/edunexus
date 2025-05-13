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
        Schema::create('user_social_links', function (Blueprint $table) {
            $table->foreignId('user_id')->references('user_id')->on('users')->constrained('users')->onDelete('cascade');
            $table->foreignId('social_link_id')->constrained()->onDelete('cascade');
            $table->primary(['user_id', 'social_link_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_social_links');
    }
};
