<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            // Процент роялти для автора (например, 50.0)
            $table->float('royalty_percent')->nullable()->after('name'); 
        });

        Schema::table('agencies', function (Blueprint $table) {
            // Процент роялти для агентства
            $table->float('royalty_percent')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('authors', fn (Blueprint $table) => $table->dropColumn('royalty_percent'));
        Schema::table('agencies', fn (Blueprint $table) => $table->dropColumn('royalty_percent'));
    }
};