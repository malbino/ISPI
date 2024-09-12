<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Persona;
use App\Models\Movimiento;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
    protected $table = 'users';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'nick',
        'email',
        'password',
        'rol', // Ensure 'rol' is included here
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = bcrypt($value);
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'id', 'id_persona');
    }    

    public function movimientos()
    {
        return $this->hasMany(Movimiento::class, 'id_operador');
    }

    public function hasRole($role)
    {
        return $this->role === $role; // Ensure 'rol' matches your column name
    }
}
