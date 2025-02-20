<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class KiotSub extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $connection = 'mongodb';
    protected $collection = 'kiosk_sub';

    protected $fillable = [
       'kiosk_id',
        'name',
        'price',
        'status',
        'quantity',
        'soft_unit',
        'token_API',
        'soft_url',
        'soft_life_time_price',
        'post_back',
        'api_quantity',
        'api_data',
        'public_token'
    ];
}
