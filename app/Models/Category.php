<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Category extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $connection = 'mongodb';
    protected $collection = 'category';

    protected $fillable = [
        'name',
        'icon',
        'id_parent',
        'detaill',
    ];
}
