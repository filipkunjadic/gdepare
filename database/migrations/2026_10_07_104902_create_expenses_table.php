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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('receiver_id');
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('RSD');
            $table->date('date');
            $table->string('payment_method');
            $table->timestamps();

            $table->unique(['user_id', 'id']);
            $table->index(['user_id', 'date']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign(['user_id', 'receiver_id'])
                ->references(['user_id', 'id'])
                ->on('receivers')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
