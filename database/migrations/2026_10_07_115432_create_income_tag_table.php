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
        Schema::table('incomes', function (Blueprint $table) {
            $table->unique(['user_id', 'id']);
        });

        Schema::create('income_tag', function (Blueprint $table) {
            $table->foreignId('user_id');
            $table->foreignId('income_id');
            $table->foreignId('tag_id');
            $table->primary(['income_id', 'tag_id']);

            $table->foreign(['user_id', 'income_id'])
                ->references(['user_id', 'id'])
                ->on('incomes')
                ->cascadeOnDelete();

            $table->foreign(['user_id', 'tag_id'])
                ->references(['user_id', 'id'])
                ->on('tags')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('income_tag');

        Schema::table('incomes', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'id']);
        });
    }
};
