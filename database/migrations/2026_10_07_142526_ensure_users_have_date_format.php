<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'date_format')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('date_format', 10)->default('d.m.Y');
            });
        }
    }

    /**
     * The original date-format migration owns removal of this column.
     */
    public function down(): void {}
};
