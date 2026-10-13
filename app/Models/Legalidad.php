<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Legalidad extends Model
{
    protected $table = 'legalidad';
    
    protected $casts = ['inicio_cargo' => 'date',
    'fin_cargo' => 'date',
    'fecha_inicio_reeleccion' => 'datetime',
    'fecha_validacion' => 'datetime',
    'ya_reelegido' => 'boolean',];
    
    protected $fillable = [
        'consejo_id',
        'integrante_id',
        'inicio_cargo',
        'fin_cargo',
        'periodo_habil',
        'estatus_reeleccion',
        'motivo_rechazo',
        'fecha_inicio_reeleccion',
        'fecha_validacion',
        'validado_por',
        //docs
        'doc_nombramiento',
        'doc_carta_reeleccion',
        'doc_otros',
        //control
        'ya_reelegido',

    ];

    public function integrante()
    {
        return $this->belongsTo(Integrante::class)->withDefault();
    }

    public function consejo()
    {
        return $this->belongsTo(Consejo::class);
    }
}
