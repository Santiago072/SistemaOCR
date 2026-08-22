<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipanteFinal extends Model
{
    use HasFactory;

    protected $table = 'participantes_finales';
    public $timestamps = false;

    protected $fillable = [
        'ficha_id',
        'tipo_documento',
        'numero_documento',
        'nombres',
        'apellidos',
        'nombre_completo',
        'genero',
        'fecha_nacimiento',
        'rh',
        'estado_inscripcion',
        'origen_validacion'
    ];

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(Ficha::class, 'ficha_id');
    }
}
