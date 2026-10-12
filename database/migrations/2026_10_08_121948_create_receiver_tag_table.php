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
        Schema::create('receiver_tag', function (Blueprint $table) {
            $table->foreignId('user_id');
            $table->foreignId('receiver_id');
            $table->foreignId('tag_id');
            $table->timestamps();

            $table->primary(['receiver_id', 'tag_id']);

            $table->foreign(['user_id', 'receiver_id'])
                ->references(['user_id', 'id'])
                ->on('receivers')
                ->cascadeOnDelete();

            $table->foreign(['user_id', 'tag_id'])
                ->references(['user_id', 'id'])
                ->on('tags')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receiver_tag');
    }
};
