<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class balance_log extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $connection = 'mongodb';
    protected $collection = 'balance_log';

    protected $fillable = [
        'user_id',
        'id_balance',
        'action_user',
        'transaction_status',
        'last_balance',
        'current_balance',
        'balance',
        'status',
    ];
}
