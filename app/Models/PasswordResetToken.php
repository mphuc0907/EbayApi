<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as Model;

class PasswordResetToken extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'password_reset_tokens';

    protected $fillable = [
        'email',
        'token',
        'created_at',
    ];
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    // Kiểm tra email và token có hợp lệ không
    public function checkEmailAndToken($token, $email) {
        if($this->where('email', $email)->where('token', $token)->count()) {
            return $this->where('email', $email)->where('token', $token)->first();
        }
        return false;
    }
}
