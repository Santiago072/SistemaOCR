<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AspiranteExcel extends Model
{
    use HasFactory;

    protected $table = 'aspirantes_excel';
    public $timestamps = false;

    protected $fillable = [
        'ficha_id',
        'tipo_documento',
        'numero_documento',
        'nombre_completo',
        'estado_inscripcion'
    ];

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(Ficha::class, 'ficha_id');
    }
}
