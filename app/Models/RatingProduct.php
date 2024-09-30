<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class RatingProduct extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $connection = 'mongodb';
    protected $collection = 'rating_product';

    protected $fillable = [
        'comment',
        'imageGallery',
        'id_product',
        'star',
        'id_parent',
        'kiosk_id',
        'order_id',
        'reply',
        'name_user',
        'avatar_user',
        'email',
        'status',
        'user_id',
        'total_like',
    ];
}
