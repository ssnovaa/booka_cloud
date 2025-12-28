<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agency extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'payment_details', 'royalty_percent'];

    public function books()
    {
        return $this->hasMany(ABook::class);
    }
}