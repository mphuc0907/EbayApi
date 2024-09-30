<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Wishlist extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $connection = 'mongodb';
    protected $collection = 'wishlist';

    protected $fillable = [
        'kiosk_id',
        'user_id'
    ];
}
