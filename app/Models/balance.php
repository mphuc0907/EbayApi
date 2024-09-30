<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class balance extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $connection = 'mongodb';
    protected $collection = 'balance';

    protected $fillable = [
      'user_id',
      'balance',
      'id_wallet',
      'topup',
      'giftcode',
      'balance_support',
    ];
}
