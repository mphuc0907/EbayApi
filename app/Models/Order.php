<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Order extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $connection = 'mongodb';
    protected $collection = 'orders';

    protected $fillable = [
        'order_code',
        'user_id',
        'accept_date',
        'kiosk_sub_name',
        'kiosk_sub_id',
        'ref_user_id',
        'id_seller',
        'name_seller',
        'price',
        'status_kiot',
        'is_report',
        'detailed_request',
        'order_requirement',
        'total_price',
        'is_api',
        'code_vorcher',
        'is_ratting',
        'promotion_id',
        'soft_use_time',
        'service_waitingdays',
        'service_product',
        'refund_money',
        'quality',
        'kiosk_id',
        'finish_date',
        'sale_percent',
        'reduce_amount',
        'admin_amount',
        'reseller_amount',
        'status',
        'name_user_buy',
        'name_ref',
    ];
}
