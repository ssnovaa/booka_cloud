<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('a_books', function (Blueprint $table) {
            // Кто получает деньги за ЭТУ книгу
            $table->string('agency_name')->nullable()->after('author_id');
            $table->text('payment_details')->nullable()->after('agency_name');
        });
    }

    public function down(): void
    {
        Schema::table('a_books', function (Blueprint $table) {
            $table->dropColumn(['agency_name', 'payment_details']);
        });
    }
};