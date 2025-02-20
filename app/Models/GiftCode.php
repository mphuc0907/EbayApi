<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Auth\User as Authenticatable;

class GiftCode extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $connection = 'mongodb';
    protected $collection = 'gift_codes';
    protected $fillable = [
        'code', 'type', 'amount', 'status', 'used_by_type', 'created_by', 'used_by','note'
    ];
}
