<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Services\CreditsService; // ᐊ===== 'USE' ОСТАЕТСЯ

/**
 * Модель користувача.
 *
 * Поля:
 * - id: int
 * - name: string
 * - email: string
 * - password: string
 * - is_paid: bool
 * - is_admin: bool    ← прапорець адміністратора
 * - paid_until: \Illuminate\Support\Carbon|null  ← ДОДАНО
 * - google_id: string|null
 * - avatar_url: string|null
 * - email_verified_at: \Illuminate\Support\Carbon|null
 * - google_purchase_token: string|null  // ⚠️ ДОДАНО
 * - google_product_id: string|null    // ⚠️ ДОДАНО
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Поля, дозволені для масового присвоєння.
     * З міркувань безпеки сюди НЕ додаємо is_admin.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_paid',
        'google_id',
        'avatar_url',
        // 'paid_until' свідомо не додаємо у fillable, щоб не масово присвоювати з клієнта
        
        // ⚠️ ДОДАНО: Ці поля встановлюються сервером, але додамо їх про всяк випадок
        'google_purchase_token',
        'google_product_id',
    ];

    /**
     * Поля, які мають бути приховані під час серіалізації.
     */
    protected $hidden = [
        'password',
        'remember_token',
        // ⚠️ ДОДАНО: Ховаємо токен від клієнта
        'google_purchase_token',
    ];

    /**
     * Приведення типів атрибутів моделі.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_paid'           => 'boolean',
            'is_admin'          => 'boolean',
            'paid_until'        => 'datetime', // ← ДОДАНО: для коректного ISO в JSON
        ];
    }

    /**
     * Зв’язок «обрані книги».
     */
    public function favoriteBooks()
    {
        return $this->belongsToMany(\App\Models\ABook::class, 'a_book_user')->withTimestamps();
    }

    // --- ДОДАНО ВИПРАВЛЕННЯ ---
    /**
     * Аліас для зв'язку «обрані книги» (для $user->load('favorites')).
     * Це виправляє помилку "Call to undefined relationship [favorites]".
     */
    public function favorites()
    {
        // Цей код ідентичний 'favoriteBooks()'
        return $this->belongsToMany(\App\Models\ABook::class, 'a_book_user')->withTimestamps();
    }
    // --- КІНЕЦЬ ВИПРАВЛЕННЯ ---

    /**
     * Зв’язок «прослуховування».
     */
    public function listens()
    {
        return $this->hasMany(\App\Models\Listen::class);
    }

    /**
     * Отримати унікальні книги, які користувач слухав,
     * із завантаженим автором книги.
     * Повертає Builder для подальшої кастомізації запиту.
     */
    public function listenedBooks()
    {
        return \App\Models\ABook::whereIn('id', function ($query) {
            $query->select('a_book_id')
                  ->from('listens')
                  ->where('user_id', $this->id);
        })->with('author');
    }

    // ᐊ===============================================================
    //   ✅✅✅ ОБНОВЛЕННОЕ ИСПРАВЛЕНИЕ ДЛЯ ОШИБКИ 500 ✅✅✅
    // ᐊ===============================================================
    /**
     * Аксессор для "кредитов" (баланса секунд).
     * Вызывается через $user->credits или $user->append('credits').
     *
     * @return array
     */
    public function getCreditsAttribute(): array
    {
        /** @var \App\Services\CreditsService $service */
        $service = app(CreditsService::class); 
        
        // ‼️ ИСПРАВЛЕНО: 
        // Вызываем `getSeconds()`, который существует в CreditsService,
        // и передаем $this->id (int), как он ожидает.
        $seconds = $service->getSeconds($this->id);
        
        // Возвращаем массив, который ожидает API (и контроллер)
        return [
            'seconds_left' => $seconds,
        ];
    }
    // ᐊ===============================================================
    //   КІНЕЦЬ ВИПРАВЛЕННЯ
    // ᐊ===============================================================
}