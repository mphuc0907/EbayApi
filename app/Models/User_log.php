<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Auth\User as Authenticatable;

class User_log extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $connection = 'mongodb';
    protected $collection = 'log_user';

    protected $fillable = [
        'user_agent',
        'ip',
        'status',
        'description',
        'user_id',
    ];
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
