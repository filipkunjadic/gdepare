<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->char('color', 7)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tags')->whereNull('color')->update(['color' => '#e2e8f0']);
        Schema::table('tags', function (Blueprint $table) {
            $table->char('color', 7)->nullable(false)->default('#6366f1')->change();
        });
    }
};
