<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Створюємо новий файл міграції, щоб безпечно додати стовпці

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Перевіряємо, чи існує таблиця
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                
                // Додаємо стовпець для токена покупки, лише якщо його немає
                if (!Schema::hasColumn('users', 'google_purchase_token')) {
                    // Використовуємо ->text() тому що токени бувають ДУЖЕ довгими
                    $table->text('google_purchase_token')->nullable()->after('paid_until');
                }
                
                // Додаємо стовпець для ID продукту, лише якщо його немає
                if (!Schema::hasColumn('users', 'google_product_id')) {
                    $table->string('google_product_id')->nullable()->after('google_purchase_token');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'google_purchase_token')) {
                    $table->dropColumn('google_purchase_token');
                }
                if (Schema::hasColumn('users', 'google_product_id')) {
                    $table->dropColumn('google_product_id');
                }
            });
        }
    }
};