<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class kiosk_sub_upload_history extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $connection = 'mongodb';
    protected $collection = 'kiosk_sub_upload_history';

    protected $fillable = [
        'kiosk_sub_id',
        'file_name',
        'result',
        'status',
    ];
}
