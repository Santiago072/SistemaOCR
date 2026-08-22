<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CruceConciliacion extends Model
{
    use HasFactory;

    protected $table = 'cruce_conciliacion';
    public $timestamps = false;

    protected $fillable = [
        'ficha_id',
        'aspirante_excel_id',
        'documento_pdf_id',
        'estado_cruce',
        'similitud_nombres_porcentaje',
        'observaciones',
        'validado_manualmente',
        'fecha_validacion'
    ];

    protected $casts = [
        'similitud_nombres_porcentaje' => 'float',
        'validado_manualmente' => 'boolean',
        'fecha_validacion' => 'datetime'
    ];

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(Ficha::class, 'ficha_id');
    }

    public function aspiranteExcel(): BelongsTo
    {
        return $this->belongsTo(AspiranteExcel::class, 'aspirante_excel_id');
    }

    public function documentoPdf(): BelongsTo
    {
        return $this->belongsTo(DocumentoPdfOcr::class, 'documento_pdf_id');
    }
}
