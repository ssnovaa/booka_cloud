<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AChapter extends Model
{
    use HasFactory;

    protected $fillable = [
        'a_book_id',
        'title',
        'audio_path',
        'duration', // длительность в секундах
        'order',
    ];

    /**
     * Обратная связь: глава принадлежит книге.
     */
    public function book()
    {
        return $this->belongsTo(\App\Models\ABook::class, 'a_book_id');
    }

    /**
     * Форматирование длительности главы (секунды -> MM:SS или HH:MM:SS).
     * Этот метод вызывается в edit.blade.php
     */
    public function formattedDuration()
    {
        $seconds = $this->duration ?? 0;

        if ($seconds <= 0) {
            return '00:00';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }
}