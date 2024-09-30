<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class kiosk_sub_product extends Authenticatable
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'kiosk_sub_product';

    protected $fillable = [
        'kiosk_sub_id',
        'value',
        'status',
        "namefile",
        'sold_date',
    ];
}
