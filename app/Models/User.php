<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'must_change_password',
        'privacy_accepted',
        'privacy_accepted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    //relacion user - integrante: un usuario tiene un integrante
    public function integrante(): HasOne{
        return $this->hasOne(Integrante::class); 
    }  
    
    // Baja que mantiene desactivada la cuenta: la más reciente, siempre que el usuario
    // no vuelva a ser integrante (por ejemplo, si quedó en una nueva convocatoria).
    public function bajaVigente(): ?IntegranteBaja
    {
        if ($this->integrante) {
            return null;
        }

        return IntegranteBaja::where('user_id', $this->id)
            ->latest('fecha_baja')
            ->latest('id')
            ->first();
    }

    //relacion user - postulacion: un usuario tiene una postulacion
    public function postulacion(): HasOne{
        return $this->hasOne(Postulacion::class);
    }
}
