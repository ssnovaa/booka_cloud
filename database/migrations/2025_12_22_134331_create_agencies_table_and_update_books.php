<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Создаем справочник агентств
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('payment_details')->nullable(); // Реквизиты, IBAN и т.д.
            $table->timestamps();
        });

        // 2. Добавляем связь в книги
        Schema::table('a_books', function (Blueprint $table) {
            $table->foreignId('agency_id')->nullable()->after('author_id')->constrained('agencies')->nullOnDelete();
            
            // Старые поля можно будет потом удалить, пока оставим для истории
            // $table->dropColumn(['agency_name', 'payment_details']); 
        });
    }

    public function down(): void
    {
        Schema::table('a_books', function (Blueprint $table) {
            $table->dropForeign(['agency_id']);
            $table->dropColumn('agency_id');
        });
        Schema::dropIfExists('agencies');
    }
};