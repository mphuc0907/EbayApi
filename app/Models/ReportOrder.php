<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class ReportOrder extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $connection = 'mongodb';
    protected $collection = 'report_order';

    protected $fillable = [
        'order_code',
        'id_order',
        'id_kiot',
        'info_report',
        'id_user',
        'id_seller',
        'name_user',
        'reason',
        'status',
        'user_complain_time',
    ];
}
