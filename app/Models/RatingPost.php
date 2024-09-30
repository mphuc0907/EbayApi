<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class RatingPost extends Authenticatable
{
  use HasFactory, Notifiable;
  protected $connection = 'mongodb';
  protected $collection = 'rating_post';

  protected $fillable = [
      'comment',
      'id_post',
      'email',
      'user_id',
      'post_owner_id',
      'parent_id',
      'donate',
      'total_like'
  ];
}
