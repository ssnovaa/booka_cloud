<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            // Кто получает деньги (Название юр. лица или представителя)
            $table->string('agency_name')->nullable()->after('name');
            
            // Платежные реквизиты (IBAN, PayPal и т.д.) - для удобства админа
            $table->text('payment_details')->nullable()->after('agency_name');
        });
    }

    public function down(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->dropColumn(['agency_name', 'payment_details']);
        });
    }
};