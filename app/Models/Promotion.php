<?php

namespace App\Models;
use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;


class Promotion extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    protected $table = 'promotions';
    protected $fillable = [
    'promotion_code',
    'unlimited',
    'description',
    'amount',
    'is_admin_created',
    'max_amount',
    'kiosk_id',
    'sub_kiosk_id',
    'type',
    'total_for_using',
    'percent',
    'start_date',
    'end_date',
    'created_user_id',
    'status',
];

    public $timestamps = true;
    protected $dates = ['start_date', 'end_date'];
}
