<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use LucasJohnston\Sso\Traits\HasSso;

class User extends Authenticatable
{
    use HasFactory, HasSso;

    protected $fillable = ['name', 'email', 'sso_id', 'blocked_at'];

    protected function casts(): array
    {
        return [
            'blocked_at' => 'datetime',
        ];
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }
}
