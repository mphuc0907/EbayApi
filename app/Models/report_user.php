<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class report_user extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $connection = 'mongodb';
    protected $collection = 'report_user';

    protected $fillable = [
        'contact',
        'content',
        'user_report',
        'user_create'
    ];
}
