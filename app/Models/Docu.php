<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Docu extends Model
{
    use HasFactory;

    protected $fillable = [
        'integrante_id',
        'tipo',
        'archivo',
        'estatus',
        'observacion',
        'validado_por',
        'validado_at',
    ];

    public function integrante(): BelongsTo
    {
        return $this->belongsTo(Integrante::class);
    }

    public function validador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }
}