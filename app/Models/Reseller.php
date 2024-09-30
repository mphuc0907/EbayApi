<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Reseller extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $connection = 'mongodb';
    protected $collection = 'resellers';

    protected $fillable = [
        'user_id',
        'kiosk_id',
        'sellers_id',
        'percent',
        'status',
        'approval_date',
        'linkresller',
        'created_date',
        'shortened_code',
        'allow_reseller',
        'cooperation_greetings',
    ];
}
