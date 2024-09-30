<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class Kiot extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $connection = 'mongodb';
    protected $collection = 'kiot';

    protected $fillable = [
        'name',
        'user_id',
        'category_parent_id',
        'category_id',
        'category_sub_id',
        'refund_person',
        'allow_reseller',
        'id_post',
        'is_private',
        'is_private',
        'is_duplicate',
        'short_des',
        'description',
        'image',
        'status',
    ];
}
