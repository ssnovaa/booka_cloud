<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Author extends Model
{
    use HasFactory;

    protected $fillable = [
		'name', 
        'agency_name',     // старое поле, если осталось
        'payment_details', // старое поле
        'royalty_percent', // ⬅️ ДОБАВЛЕНО
    ];

    public function books()
    {
        return $this->hasMany(ABook::class);
    }

    /**
     * Получить имя реального получателя денег.
     * Если указано агентство - возвращает его, иначе - имя автора.
     */
    public function getPayeeNameAttribute()
    {
        return $this->agency_name ?: $this->name;
    }
}