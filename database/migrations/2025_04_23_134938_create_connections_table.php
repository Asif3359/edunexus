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
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->references('user_id')->on('users')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->references('user_id')->on('users')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['Pending', 'Accepted', 'Rejected']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
