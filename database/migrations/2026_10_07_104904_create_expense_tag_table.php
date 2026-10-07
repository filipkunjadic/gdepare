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
        Schema::create('expense_tag', function (Blueprint $table) {
            $table->foreignId('user_id');
            $table->foreignId('expense_id');
            $table->foreignId('tag_id');
            $table->timestamps();

            $table->primary(['expense_id', 'tag_id']);

            $table->foreign(['user_id', 'expense_id'])
                ->references(['user_id', 'id'])
                ->on('expenses')
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
        Schema::dropIfExists('expense_tag');
    }
};
